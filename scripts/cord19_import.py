import os
import json
import csv
import mysql.connector
import gzip
import tarfile
import requests
from pathlib import Path
from datetime import datetime
import logging

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('cord19_import.log'),
        logging.StreamHandler()
    ]
)

# Configuration
CONFIG = {
    'db_host': 'localhost',
    'db_port': 3307,
    'db_user': 'wordpress',
    'db_password': 'wordpress',
    'db_name': 'wordpress',
    'batch_size': 100,
    'progress_file': 'import_progress.json',
    'cord19_version': '2022-06-02',
    'base_url': 'https://ai2-semanticscholar-cord-19.s3-us-west-2.amazonaws.com'
}

class CORD19Importer:
    def __init__(self):
        self.db = None
        self.cursor = None
        self.progress = self.load_progress()
        
    def load_progress(self):
        """Load or initialize progress tracking"""
        if os.path.exists(CONFIG['progress_file']):
            with open(CONFIG['progress_file'], 'r') as f:
                return json.load(f)
        return {
            'last_cord_uid': None,
            'processed': 0,
            'failed': 0,
            'last_update': None
        }
    
    def save_progress(self):
        """Save current progress"""
        self.progress['last_update'] = datetime.now().isoformat()
        with open(CONFIG['progress_file'], 'w') as f:
            json.dump(self.progress, f, indent=2)

    def connect_db(self):
        """Establish database connection"""
        try:
            self.db = mysql.connector.connect(
                host=CONFIG['db_host'],
                port=CONFIG['db_port'],
                user=CONFIG['db_user'],
                password=CONFIG['db_password'],
                database=CONFIG['db_name']
            )
            self.cursor = self.db.cursor(dictionary=True)
            logging.info("Connected to database successfully")
        except Exception as e:
            logging.error(f"Database connection failed: {e}")
            raise

    def download_cord19_files(self):
        """Download required CORD-19 files if not present"""
        base_url = CONFIG['base_url']
        version = CONFIG['cord19_version']
        
        files = {
            'metadata.csv': f'{version}/metadata.csv',
            'embeddings.tar.gz': f'{version}/cord_19_embeddings.tar.gz'
        }
        
        for local_file, remote_path in files.items():
            if not os.path.exists(local_file):
                url = f"{base_url}/{remote_path}"
                logging.info(f"Downloading {url}")
                response = requests.get(url, stream=True)
                response.raise_for_status()
                
                with open(local_file, 'wb') as f:
                    for chunk in response.iter_content(chunk_size=8192):
                        f.write(chunk)
                logging.info(f"Downloaded {local_file}")

    def check_duplicate(self, cord_uid):
        """Check if a paper is already imported"""
        self.cursor.execute(
            "SELECT post_id FROM wp_postmeta WHERE meta_key = '_cord_uid' AND meta_value = %s",
            (cord_uid,)
        )
        return self.cursor.fetchone() is not None

    def create_wordpress_post(self, paper):
        """Create a WordPress post for a paper"""
        try:
            # Prepare post content
            title = paper['title'].strip()
            content = paper['abstract'] if paper['abstract'] else ''
            
            # Insert post
            self.cursor.execute("""
                INSERT INTO wp_posts 
                (post_title, post_content, post_status, post_type, post_date, post_modified)
                VALUES (%s, %s, %s, %s, %s, %s)
            """, (
                title,
                content,
                'publish',
                'post',
                paper['publish_time'] or datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            ))
            
            post_id = self.cursor.lastrowid
            
            # Add post meta
            meta_fields = {
                '_cord_uid': paper['cord_uid'],
                '_doi': paper['doi'],
                '_authors': paper['authors'],
                '_publish_time': paper['publish_time'],
                '_license': paper['license'],
                '_url': paper['url'],
                '_wpvdb_embedded': '1'  # Mark as embedded for the plugin
            }
            
            for key, value in meta_fields.items():
                if value:  # Only insert if value exists
                    self.cursor.execute("""
                        INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
                        VALUES (%s, %s, %s)
                    """, (post_id, key, value))
            
            return post_id
        except Exception as e:
            logging.error(f"Error creating post for {paper['cord_uid']}: {e}")
            return None

    def insert_embedding(self, post_id, embedding):
        """Insert embedding into the vector database"""
        try:
            # Convert embedding to JSON string
            embedding_json = json.dumps(embedding)
            
            # Check if the embeddings table exists
            self.cursor.execute("SHOW TABLES LIKE 'wp_wpvdb_embeddings'")
            if not self.cursor.fetchone():
                logging.error("Embeddings table doesn't exist. Make sure the plugin is activated.")
                return False
            
            # Check table structure to determine the right approach
            self.cursor.execute("DESCRIBE wp_wpvdb_embeddings")
            columns = {row['Field']: row for row in self.cursor.fetchall()}
            
            # Get embedding column type to determine if we're using native vectors
            embedding_type = columns.get('embedding', {}).get('Type', '')
            using_vector_type = 'VECTOR' in embedding_type.upper()
            
            if using_vector_type:
                # Insert using MariaDB's vector function
                try:
                    self.cursor.execute("""
                        INSERT INTO wp_wpvdb_embeddings 
                        (doc_id, chunk_id, chunk_content, embedding, summary) 
                        VALUES (%s, %s, %s, VEC_FromText(%s), %s)
                    """, (
                        post_id,
                        'chunk-0',  # Single chunk per document
                        paper['abstract'] if 'abstract' in paper else '',  # Use abstract as chunk content
                        f'[{",".join(map(str, embedding))}]',
                        f"Summary of {paper['title']}" if 'title' in paper else ''  # Simple summary
                    ))
                    return True
                except Exception as e:
                    logging.error(f"Vector insert failed: {e}, trying fallback method")
                    # Fall through to fallback method
            
            # Fallback: Store as JSON string if vector type not supported
            self.cursor.execute("""
                INSERT INTO wp_wpvdb_embeddings 
                (doc_id, chunk_id, chunk_content, embedding, summary) 
                VALUES (%s, %s, %s, %s, %s)
            """, (
                post_id,
                'chunk-0',  # Single chunk per document
                paper['abstract'] if 'abstract' in paper else '',  # Use abstract as chunk content
                embedding_json,  # Store as JSON string
                f"Summary of {paper['title']}" if 'title' in paper else ''  # Simple summary
            ))
            
            return True
        except Exception as e:
            logging.error(f"Error inserting embedding for post {post_id}: {e}")
            return False

    def process_batch(self, papers, embeddings):
        """Process a batch of papers"""
        successful = 0
        for paper in papers:
            if self.check_duplicate(paper['cord_uid']):
                logging.info(f"Skipping duplicate paper {paper['cord_uid']}")
                continue
            
            # Create WordPress post
            post_id = self.create_wordpress_post(paper)
            if not post_id:
                self.progress['failed'] += 1
                continue
            
            # Insert embedding if available
            if paper['cord_uid'] in embeddings:
                # Store paper in the current scope for use in insert_embedding
                self.current_paper = paper
                if self.insert_embedding(post_id, embeddings[paper['cord_uid']]):
                    successful += 1
                else:
                    self.progress['failed'] += 1
                    # Delete the post if embedding fails to avoid orphaned posts
                    self.cursor.execute("DELETE FROM wp_posts WHERE ID = %s", (post_id,))
                    self.cursor.execute("DELETE FROM wp_postmeta WHERE post_id = %s", (post_id,))
                    continue
            
            # Update post meta to mark as embedded
            self.cursor.execute("""
                INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
                VALUES (%s, %s, %s)
            """, (post_id, '_wpvdb_embedded', '1'))
            
            # Add embedding metadata that the plugin expects
            self.cursor.execute("""
                INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
                VALUES (%s, %s, %s)
            """, (post_id, '_wpvdb_chunks_count', '1'))
            
            self.cursor.execute("""
                INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
                VALUES (%s, %s, %s)
            """, (post_id, '_wpvdb_embedded_date', datetime.now().strftime('%Y-%m-%d %H:%M:%S')))
            
            self.cursor.execute("""
                INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
                VALUES (%s, %s, %s)
            """, (post_id, '_wpvdb_embedded_model', 'SPECTER'))
            
            self.progress['processed'] += 1
            self.progress['last_cord_uid'] = paper['cord_uid']
            
        logging.info(f"Successfully processed {successful} papers in this batch")
        self.db.commit()
        self.save_progress()

    def run(self):
        """Main import process"""
        try:
            # Connect to database
            self.connect_db()
            
            # Check if plugin is activated by looking for the embeddings table
            self.cursor.execute("SHOW TABLES LIKE 'wp_wpvdb_embeddings'")
            if not self.cursor.fetchone():
                logging.error("The WordPress Vector Database plugin doesn't appear to be activated.")
                logging.error("Please activate the plugin before running this import script.")
                return
            
            # Check WordPress table prefix
            self.cursor.execute("SHOW TABLES LIKE '%posts'")
            tables = self.cursor.fetchall()
            if not tables:
                logging.error("Could not find WordPress tables. Check database connection.")
                return
            
            # Determine WordPress table prefix
            table_name = list(tables[0].values())[0]
            self.wp_prefix = table_name.replace('posts', '')
            logging.info(f"Using WordPress table prefix: {self.wp_prefix}")
            
            # Download CORD-19 files
            self.download_cord19_files()
            
            # Load embeddings
            logging.info("Loading embeddings...")
            embeddings = {}
            with tarfile.open('embeddings.tar.gz', 'r:gz') as tar:
                csv_files = [m for m in tar.getmembers() if m.name.endswith('.csv')]
                if not csv_files:
                    logging.error("No CSV files found in the embeddings archive")
                    return
                
                # Get the first CSV file
                member = csv_files[0]
                logging.info(f"Processing embeddings from {member.name}")
                
                f = tar.extractfile(member)
                if f:
                    line_count = 0
                    for line in f:
                        line_count += 1
                        if line_count % 10000 == 0:
                            logging.info(f"Processed {line_count} embedding lines...")
                        
                        parts = line.decode().strip().split(',')
                        if len(parts) < 2:
                            continue
                            
                        cord_uid = parts[0]
                        try:
                            embedding = [float(x) for x in parts[1:]]
                            embeddings[cord_uid] = embedding
                        except ValueError as e:
                            logging.warning(f"Error parsing embedding for {cord_uid}: {e}")
                
                logging.info(f"Loaded {len(embeddings)} embeddings from {line_count} lines")
            
            if not embeddings:
                logging.error("No embeddings were loaded. Check the embeddings file format.")
                return
            
            # Process papers in batches
            batch = []
            processed_count = 0
            
            logging.info("Starting to process papers from metadata.csv")
            with open('metadata.csv', 'r', encoding='utf-8') as f:
                reader = csv.DictReader(f)
                for paper in reader:
                    processed_count += 1
                    if processed_count % 1000 == 0:
                        logging.info(f"Examined {processed_count} papers so far...")
                    
                    # Skip papers we've already processed
                    if (self.progress['last_cord_uid'] and 
                        paper['cord_uid'] <= self.progress['last_cord_uid']):
                        continue
                    
                    # Skip papers without embeddings
                    if paper['cord_uid'] not in embeddings:
                        continue
                    
                    batch.append(paper)
                    if len(batch) >= CONFIG['batch_size']:
                        logging.info(f"Processing batch of {len(batch)} papers...")
                        self.process_batch(batch, embeddings)
                        batch = []
            
            # Process remaining papers
            if batch:
                logging.info(f"Processing final batch of {len(batch)} papers...")
                self.process_batch(batch, embeddings)
            
            logging.info("Import completed successfully")
            logging.info(f"Processed: {self.progress['processed']}")
            logging.info(f"Failed: {self.progress['failed']}")
            
        except Exception as e:
            logging.error(f"Import failed: {e}")
            import traceback
            logging.error(traceback.format_exc())
        finally:
            if self.db:
                self.db.close()

if __name__ == "__main__":
    importer = CORD19Importer()
    importer.run()