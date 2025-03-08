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
    'batch_size': 500,
    'progress_file': 'import_progress.json',
    'cord19_version': '2022-06-02',
    'base_url': 'https://ai2-semanticscholar-cord-19.s3-us-west-2.amazonaws.com',
    'connection_pool_size': 5,
    'connection_pool_name': 'wpvdb_pool',
    'use_prepared_statements': True
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
        """Establish database connection with connection pooling"""
        try:
            # Use connection pooling for better performance
            from mysql.connector import pooling
            
            # Check if pool exists and create if it doesn't
            try:
                self.pool = mysql.connector.pooling.MySQLConnectionPool(
                    pool_name=CONFIG['connection_pool_name'],
                    pool_size=CONFIG['connection_pool_size'],
                    host=CONFIG['db_host'],
                    port=CONFIG['db_port'],
                    user=CONFIG['db_user'],
                    password=CONFIG['db_password'],
                    database=CONFIG['db_name'],
                    use_pure=True,  # Use pure Python implementation for thread safety
                    autocommit=False,  # We'll handle commits explicitly in batches
                    buffered=True  # Use buffered cursors for better performance
                )
                logging.info(f"Created connection pool with {CONFIG['connection_pool_size']} connections")
            except mysql.connector.errors.PoolError:
                # Pool already exists
                pass
            
            # Get a connection from the pool
            self.db = mysql.connector.connect(
                pool_name=CONFIG['connection_pool_name']
            )
            
            self.cursor = self.db.cursor(dictionary=True, buffered=True)
            logging.info("Connected to database successfully using connection pool")
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
        """Process a batch of papers using bulk inserts for better performance"""
        successful = 0
        failed = 0
        
        try:
            # Prepare data structures for bulk operations
            posts_to_insert = []
            post_meta_to_insert = []
            embeddings_to_insert = []
            cord_uid_to_post_id = {}
            
            # Begin transaction
            self.db.start_transaction()
            
            # STEP 1: Insert all posts in bulk
            for paper in papers:
                if self.check_duplicate(paper['cord_uid']):
                    logging.info(f"Skipping duplicate paper {paper['cord_uid']}")
                    continue
                
                # Skip papers without embeddings
                if paper['cord_uid'] not in embeddings:
                    continue
                
                title = paper['title'].strip()
                content = paper['abstract'] if paper['abstract'] else ''
                current_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
                pub_time = paper['publish_time'] or current_time
                
                posts_to_insert.append((
                    title,
                    content,
                    'publish',
                    'post',
                    pub_time,
                    current_time
                ))
            
            # Execute bulk post insert
            if posts_to_insert:
                # Use executemany with larger batch size for better performance
                batch_size = 100  # Adjust based on your database capability
                for i in range(0, len(posts_to_insert), batch_size):
                    batch = posts_to_insert[i:i+batch_size]
                    self.cursor.executemany("""
                        INSERT INTO wp_posts 
                        (post_title, post_content, post_status, post_type, post_date, post_modified)
                        VALUES (%s, %s, %s, %s, %s, %s)
                    """, batch)
                
                # Get the starting ID of the inserted rows
                self.cursor.execute("SELECT LAST_INSERT_ID()")
                first_post_id = self.cursor.fetchone()['LAST_INSERT_ID()']
                
                # Map cord_uids to post_ids
                for i, paper in enumerate([p for p in papers if p['cord_uid'] in embeddings and not self.check_duplicate(p['cord_uid'])]):
                    post_id = first_post_id + i
                    cord_uid_to_post_id[paper['cord_uid']] = post_id
                    
                    # Prepare post meta
                    meta_fields = {
                        '_cord_uid': paper['cord_uid'],
                        '_doi': paper['doi'],
                        '_authors': paper['authors'],
                        '_publish_time': paper['publish_time'],
                        '_license': paper['license'],
                        '_url': paper['url'],
                        '_wpvdb_embedded': '1',
                        '_wpvdb_chunks_count': '1',
                        '_wpvdb_embedded_date': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                        '_wpvdb_embedded_model': 'SPECTER'
                    }
                    
                    for key, value in meta_fields.items():
                        if value:  # Only insert if value exists
                            post_meta_to_insert.append((post_id, key, value))
                    
                    # Prepare embeddings data
                    embedding_data = embeddings[paper['cord_uid']]
                    embedding_json = json.dumps(embedding_data)
                    embeddings_to_insert.append((
                        post_id,
                        'chunk-0',
                        paper['abstract'] if paper['abstract'] else '',
                        f'[{",".join(map(str, embedding_data))}]',
                        f"Summary of {paper['title']}" if paper['title'] else ''
                    ))
            
            # STEP 2: Bulk insert all post meta
            if post_meta_to_insert:
                # Insert post meta in batches
                batch_size = 500  # Typically can handle larger batches for meta
                for i in range(0, len(post_meta_to_insert), batch_size):
                    batch = post_meta_to_insert[i:i+batch_size]
                    self.cursor.executemany("""
                        INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
                        VALUES (%s, %s, %s)
                    """, batch)
            
            # STEP 3: Bulk insert all embeddings
            # First get table structure to check if vector type is supported
            self.cursor.execute("DESCRIBE wp_wpvdb_embeddings")
            columns = {row['Field']: row for row in self.cursor.fetchall()}
            embedding_type = columns.get('embedding', {}).get('Type', '')
            using_vector_type = 'VECTOR' in embedding_type.upper()
            
            if using_vector_type and embeddings_to_insert:
                try:
                    # Insert embeddings in smaller batches as they're larger
                    batch_size = 50
                    for i in range(0, len(embeddings_to_insert), batch_size):
                        batch = embeddings_to_insert[i:i+batch_size]
                        self.cursor.executemany("""
                            INSERT INTO wp_wpvdb_embeddings 
                            (doc_id, chunk_id, chunk_content, embedding, summary) 
                            VALUES (%s, %s, %s, VEC_FromText(%s), %s)
                        """, batch)
                except Exception as e:
                    logging.error(f"Bulk vector insert failed: {e}, trying individual inserts")
                    # If bulk insert fails, try individual inserts
                    for embed_data in embeddings_to_insert:
                        try:
                            self.cursor.execute("""
                                INSERT INTO wp_wpvdb_embeddings 
                                (doc_id, chunk_id, chunk_content, embedding, summary) 
                                VALUES (%s, %s, %s, VEC_FromText(%s), %s)
                            """, embed_data)
                            successful += 1
                        except Exception as e2:
                            logging.error(f"Individual vector insert failed for post {embed_data[0]}: {e2}")
                            failed += 1
                            # Delete the post if embedding fails
                            self.cursor.execute("DELETE FROM wp_posts WHERE ID = %s", (embed_data[0],))
                            self.cursor.execute("DELETE FROM wp_postmeta WHERE post_id = %s", (embed_data[0],))
            else:
                # Use JSON string fallback approach
                if embeddings_to_insert:
                    # Modify the entries to use JSON string instead of VEC_FromText
                    json_embeddings_to_insert = []
                    for embed_data in embeddings_to_insert:
                        # Replace the vector text with JSON string
                        json_embeddings_to_insert.append((
                            embed_data[0],  # doc_id
                            embed_data[1],  # chunk_id
                            embed_data[2],  # chunk_content
                            embed_data[3].replace("VEC_FromText(", "").replace(")", ""),  # JSON string
                            embed_data[4]   # summary
                        ))
                    
                    # Insert JSON embeddings in batches
                    batch_size = 100
                    for i in range(0, len(json_embeddings_to_insert), batch_size):
                        batch = json_embeddings_to_insert[i:i+batch_size]
                        self.cursor.executemany("""
                            INSERT INTO wp_wpvdb_embeddings 
                            (doc_id, chunk_id, chunk_content, embedding, summary) 
                            VALUES (%s, %s, %s, %s, %s)
                        """, batch)
            
            # Complete the transaction
            self.db.commit()
            
            # Update progress
            successful = len(cord_uid_to_post_id)
            self.progress['processed'] += successful
            self.progress['failed'] += failed
            
            if papers:
                self.progress['last_cord_uid'] = papers[-1]['cord_uid']
                
            logging.info(f"Successfully processed {successful} papers in this batch")
            self.save_progress()
            
        except Exception as e:
            logging.error(f"Batch processing failed: {e}")
            import traceback
            logging.error(traceback.format_exc())
            self.db.rollback()
            self.progress['failed'] += len(papers)
        
        return successful

    def run(self):
        """Main import process with optimized memory usage"""
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
            
            # Pre-load metadata to get a list of papers for efficient processing
            paper_dict = {}
            logging.info("Pre-loading metadata.csv to build paper index...")
            with open('metadata.csv', 'r', encoding='utf-8') as f:
                reader = csv.DictReader(f)
                for paper in reader:
                    # Skip papers we've already processed
                    if (self.progress['last_cord_uid'] and 
                        paper['cord_uid'] <= self.progress['last_cord_uid']):
                        continue
                    
                    paper_dict[paper['cord_uid']] = paper
            
            logging.info(f"Pre-loaded {len(paper_dict)} papers from metadata")
            
            # Process embeddings and papers in multiple batches to save memory
            logging.info("Starting to process embeddings and papers in batches")
            
            # Open the embeddings tarfile and read in chunks
            with tarfile.open('embeddings.tar.gz', 'r:gz') as tar:
                csv_files = [m for m in tar.getmembers() if m.name.endswith('.csv')]
                if not csv_files:
                    logging.error("No CSV files found in the embeddings archive")
                    return
                
                # Get the first CSV file
                member = csv_files[0]
                logging.info(f"Processing embeddings from {member.name}")
                
                f = tar.extractfile(member)
                if not f:
                    logging.error("Failed to extract embeddings file")
                    return
                
                # Process embeddings in chunks to save memory
                batch_papers = []
                current_embeddings = {}
                line_count = 0
                batch_count = 0
                
                logging.info("Reading embeddings and processing in chunks...")
                
                # Read embeddings file line by line
                for line in f:
                    line_count += 1
                    
                    if line_count % 10000 == 0:
                        logging.info(f"Read {line_count} embedding lines, processed {batch_count} batches...")
                    
                    parts = line.decode().strip().split(',')
                    if len(parts) < 2:
                        continue
                        
                    cord_uid = parts[0]
                    
                    # Skip if paper not in our filtered list or already processed
                    if cord_uid not in paper_dict:
                        continue
                    
                    try:
                        embedding = [float(x) for x in parts[1:]]
                        current_embeddings[cord_uid] = embedding
                        batch_papers.append(paper_dict[cord_uid])
                        
                        # Once we have enough papers for a batch, process them
                        if len(batch_papers) >= CONFIG['batch_size']:
                            logging.info(f"Processing batch of {len(batch_papers)} papers...")
                            self.process_batch(batch_papers, current_embeddings)
                            
                            # Clear for next batch to free memory
                            batch_papers = []
                            current_embeddings = {}
                            batch_count += 1
                            
                            # Save progress more frequently
                            self.save_progress()
                    except ValueError as e:
                        logging.warning(f"Error parsing embedding for {cord_uid}: {e}")
                
                # Process any remaining papers
                if batch_papers:
                    logging.info(f"Processing final batch of {len(batch_papers)} papers...")
                    self.process_batch(batch_papers, current_embeddings)
            
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