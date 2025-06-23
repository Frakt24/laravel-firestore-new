# Laravel Firestore

A Laravel package for easy integration with Google Cloud Firestore using Google API Client and Google Auth.

## Installation

You can install the package via composer:

```bash
composer require your-vendor/laravel-firestore
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag="firestore-config"
```

Then, add your Firestore credentials to your `.env` file:

```
FIRESTORE_PROJECT_ID=your-project-id
FIRESTORE_KEY_FILE=/path/to/service-account.json
FIRESTORE_DATABASE_ID=(default)

# Authentication token caching (optional)
FIRESTORE_USE_TOKEN_CACHE=true
FIRESTORE_TOKEN_CACHE_TIME=3500
```

### Authentication Token Caching

By default, the package caches authentication tokens to reduce API calls to Google's authentication servers. This improves performance by avoiding unnecessary authentication requests. You can configure this behavior in your `.env` file:

```
# Disable token caching
FIRESTORE_USE_TOKEN_CACHE=false

# Or adjust the cache duration (in seconds, default is 3500 - just under 1 hour)
FIRESTORE_TOKEN_CACHE_TIME=1800
```

## Usage

### Basic Usage

```php
use YourVendor\LaravelFirestore\Facades\Firestore;

// Get a collection reference
$collection = Firestore::collection('users');

// Get a document reference
$document = Firestore::document('users', 'user-id');
```

### Working with Documents

```php
// Create or update a document
$document->createOrUpdateDocument([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
    'isActive' => true,
    'createdAt' => new DateTime(),
    'tags' => ['developer', 'laravel'],
    'metadata' => [
        'lastLogin' => new DateTime(),
        'preferences' => [
            'theme' => 'dark',
            'notifications' => true
        ]
    ]
]);

// Get document data
$data = $document->retrieveDocumentData();

// Check if document exists
if ($document->checkDocumentExistence()) {
    // Document exists
}

// Update specific fields
$document->updateDocumentFields([
    'name' => 'Jane Doe',
    'age' => 31
]);

// Delete a document
$document->deleteDocument();

// Access a subcollection of a document
$subcollection = $document->getSubcollectionReference('posts');
```

### Working with Collections

```php
// Add a new document with auto-generated ID
$newDocument = $collection->add([
    'name' => 'New User',
    'email' => 'new@example.com'
]);

// Get the ID of the new document
$newDocumentId = $newDocument->getDocumentId();

// List documents in a collection
$documents = $collection->listDocuments(20); // Limit to 20 documents

// Pagination
$nextPageDocuments = $collection->listDocuments(20, $documents['nextPageToken']);
```

### Querying Collections

```php
// Create a query
$query = $collection->query();

// Add filters
$query->where('age', '>=', 21)
      ->where('isActive', '==', true)
      ->where('tags', 'array-contains', 'developer');

// Order results
$query->orderBy('name', 'asc')
      ->orderBy('age', 'desc');

// Limit results
$query->limit(10);

// Execute the query
$results = $query->get();

// Process results
foreach ($results as $result) {
    $documentId = $result['id'];
    $documentRef = $result['ref'];
    $documentData = $result['data'];
    
    // Do something with the data
    echo $documentData['name'];
}
```

### Advanced Queries

```php
// Compound queries
$query->where('age', '>=', 21)
      ->where('age', '<=', 65);

// Array membership
$query->where('tags', 'array-contains-any', ['developer', 'designer']);

// Value in array
$query->where('status', 'in', ['active', 'pending']);

// Pagination with cursors
$query->orderBy('name')
      ->startAt(['John'])
      ->limit(10);
```

### Using the Google API Client Directly

If you need more advanced functionality, you can access the underlying Google API Client:

```php
$client = Firestore::getClient();
// Now you can use all methods from the Google API Client
```

## Error Handling

The package will throw exceptions when errors occur. You should wrap your Firestore operations in try-catch blocks:

```php
try {
    $document->createOrUpdateDocument($data);
} catch (\Exception $e) {
    // Handle the error
    Log::error('Firestore error: ' . $e->getMessage());
}
```

## Testing

```bash
composer test
```

## Security

If you discover any security related issues, please email your.email@example.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
