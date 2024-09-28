# File-Based Vector Store

This repository provides a simple vector store with basic insertion and search capabilities. It supports Euclidean and 
cosine distance as the available metrics for measuring similarity.

## Initializing the Store

To get started, instantiate a new `VectorStore` object. This will create a local file (if it doesn’t already exist) 
with a specified fixed vector length.

```php
$vectorStore = new VectorStore('vectors.dat', 1536);
```

## Inserting New Vectors

**Note:** Currently, all newly added vectors are appended to the file.

Alongside the vector, a custom payload can be provided, which will be converted to JSON and stored in the database 
file.

```php
$vectorStore->addVector($vector, ['id' => $i]);
```

## Searching for Neighbors

You can search for neighboring vectors using one of the two supported distance metrics (Euclidean or cosine). The 
search query will always return the payload of the `n` closest vectors.

```php
$results = $vectorStore->search($searchVector, $limit, 'cosine');
```