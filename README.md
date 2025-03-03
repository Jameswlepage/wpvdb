# WordPress Vector Database (WPVDB)

A WordPress plugin that enables vector database functionality for semantic search and AI-powered content recommendations.

## Features

- Store and query vector embeddings in your WordPress database
- Works with both MySQL 9.0+ (native VECTOR type) and MariaDB 11.7+ (VECTOR type + index)
- Fallback to JSON storage for compatibility with older database versions
- Generate embeddings using OpenAI's API (or other providers)
- Extend WP_Query with vector similarity search
- REST API for embedding generation and vector search
- Background processing with Action Scheduler

## Requirements

- WordPress 5.8+
- PHP 7.4+
- For optimal performance:
  - MySQL 9.0+ (with native VECTOR type) OR
  - MariaDB 11.7+ (with VECTOR type and VECTOR index)
- Action Scheduler (recommended but not required)

## Installation

1. Upload the plugin files to the `/wp-content/plugins/wpvdb` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure your API key and settings through the Vector DB settings page.

## Using the Docker Testing Environment

This plugin includes a Docker setup to test with both MySQL 9.0+ and MariaDB 11.7+ vector databases.

### Starting the Environment

```bash
docker-compose up -d
```

This will start two separate WordPress instances:

- **WordPress with MySQL 9.0+**: http://localhost:9080
- **WordPress with MariaDB 11.7+**: http://localhost:9081

Both instances have:
- Admin username: `admin` 
- Admin password: `password`

### Database Administration

- **MySQL Admin (Adminer)**: http://localhost:9180
- **MariaDB Admin (Adminer)**: http://localhost:9181
- Login with:
  - System: MySQL (or MariaDB)
  - Server: mysql (or mariadb)
  - Username: root
  - Password: wordpress
  - Database: wordpress

### Testing Vector Functionality

#### For MySQL 9.0+:

1. Visit http://localhost:9080/wp-admin
2. Configure API keys in Vector DB settings
3. Generate embeddings for sample content
4. Test vector search using the native MySQL vector type

#### For MariaDB 11.7+:

1. Visit http://localhost:9081/wp-admin
2. Configure API keys in Vector DB settings
3. Generate embeddings for sample content
4. Test vector search using MariaDB's vector implementation

### Database Comparison

| Feature | MySQL 9.0+ | MariaDB 11.7+ |
|---------|------------|---------------|
| Vector Type | VECTOR(dim) | VECTOR(dim) |
| Max Dimensions | 16383 | No stated limit |
| Vector Index | No native support | VECTOR index type |
| Algorithm | N/A | Modified HNSW |
| Distance Functions | Requires custom functions | Built-in (VEC_DISTANCE_EUCLIDEAN, etc.) |
| Optimization | Minimal | Specialized for vector search |

## Usage

### Generating Embeddings

```php
// Generate an embedding for a text chunk
$api_key = 'your-api-key';
$embedding = WPVDB\Core::get_embedding('Your text to embed');

// Store the embedding
WPVDB\REST::insert_embedding_row(
    $post_id,
    'chunk-id',
    'Your text to embed',
    'Optional summary',
    $embedding
);
```

### Vector Search

```php
// Search posts by vector similarity
$args = array(
    'post_type' => 'post',
    'posts_per_page' => 5,
    'vector_query' => array(
        'query' => 'Your search query',
        'model' => 'text-embedding-3-small'
    )
);

$query = new WP_Query($args);
```

## REST API Endpoints

- `GET /wp-json/vdb/v1/embeddings` - List embeddings
- `POST /wp-json/vdb/v1/embeddings` - Create embeddings
- `POST /wp-json/vdb/v1/query` - Perform vector search

## Documentation

For more detailed documentation, please see the [Wiki](https://github.com/your-username/wpvdb/wiki).

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the GPL v2 or later.