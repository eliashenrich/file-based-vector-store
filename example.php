<?php
include 'VectorStore.php';

$dataFile = './vectors.dat';

$storeExists = file_exists($dataFile);

$vectorStore = new VectorStore('vectors.dat', 1536);

if (!$storeExists) {
    echo "Initializing sample data...\n";

    for ($i = 0; $i < 1000; $i++) {
        $vector = [];
        for ($j = 0; $j < 1536; $j++) {
            $vector[] = mt_rand() / mt_getrandmax();
        }

        $vectorStore->addVector($vector, ['id' => $i]);
    }
}

echo "Searching for similar vectors...\n";
$randomVector = array_map(function() {
    return mt_rand() / mt_getrandmax();
}, range(1, 1536));

$results = $vectorStore->search($randomVector, 2, 'cosine');

echo "Results:\n";
print_r($results);