<?php

class VectorStore
{
    private string $dataFile;
    private int $vectorDimension;
    private int $vectorByteSize;

    /**
     * VectorStore constructor.
     * 
     * @param string $dataFile
     * @param int $vectorDimension
     */
    public function __construct(string $dataFile, int $vectorDimension)
    {
        $this->dataFile = $dataFile;
        $this->vectorDimension = $vectorDimension;
        $this->vectorByteSize = $vectorDimension * 4; // 4 bytes per float

        // Initialize the data file if it doesn't exist
        if (!file_exists($this->dataFile)) {
            $this->initializeDataFile();
        }
    }

    /**
     * Initialize the data file with the given vector dimension.
     * 
     * @return void
     */
    private function initializeDataFile(): void
    {
        $header = pack('N', $this->vectorDimension); // Store the dimension as an unsigned long
        file_put_contents($this->dataFile, $header);
    }

    /**
     * Add a vector and its payload to the store.
     * 
     * @param array $vector
     * @param array $metadata
     * @throws \InvalidArgumentException
     * 
     * @return void
     */
    public function addVector(array $vector, array $metadata): void
    {
        if (count($vector) !== $this->vectorDimension) {
            throw new InvalidArgumentException('Vector dimension does not match.');
        }

        $handle = fopen($this->dataFile, 'ab');
        $vectorData = pack('f*', ...$vector);
        $metaDataJson = json_encode($metadata);
        $metaDataLength = pack('N', strlen($metaDataJson));

        fwrite($handle, $vectorData);
        fwrite($handle, $metaDataLength);
        fwrite($handle, $metaDataJson);
        fclose($handle);
    }

    /**
     * Search for the k-nearest vectors to the input vector.
     * 
     * @param float[] $inputVector
     * @param int $k
     * @param string $metric
     * 
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * 
     * @return array
     */
    public function search(array $inputVector, int $k, string $metric = 'euclidean'): array
    {
        if (count($inputVector) !== $this->vectorDimension) {
            throw new InvalidArgumentException('Input vector dimension does not match.');
        }

        $handle = fopen($this->dataFile, 'rb');
        fread($handle, 4); // Skip the header

        $inputVectorArray = $inputVector;

        $heap = new SplPriorityQueue();
        $heap->setExtractFlags(SplPriorityQueue::EXTR_DATA);

        $offset = 4;

        while (!feof($handle)) {
            $vectorData = fread($handle, $this->vectorByteSize);
            if (strlen($vectorData) < $this->vectorByteSize) {
                break;
            }
            $vector = unpack('f*', $vectorData);

            // Read metadata length
            $metaDataLengthData = fread($handle, 4);
            if (strlen($metaDataLengthData) < 4) {
                break;
            }
            $metaDataLength = unpack('N', $metaDataLengthData)[1];

            if ($metaDataLength <= 0) {
                throw new RuntimeException("Invalid metadata length: $metaDataLength");
            }

            $metadataOffset = $offset + $this->vectorByteSize + 4;

            // Skip metadata
            fseek($handle, $metaDataLength, SEEK_CUR);

            $offset += $this->vectorByteSize + 4 + $metaDataLength;

            if ($metric === 'euclidean') {
                $distance = $this->euclideanDistanceSquared($inputVectorArray, $vector);
            } elseif ($metric === 'cosine') {
                $distance = $this->cosineDistance($inputVectorArray, $vector);
            } else {
                throw new InvalidArgumentException('Unknown distance metric.');
            }

            $priority = -$distance;
            $heap->insert([
                'distance' => $distance,
                'metadataOffset' => $metadataOffset,
                'metaDataLength' => $metaDataLength,
            ], $priority);

            if ($heap->count() > $k) {
                $heap->extract();
            }
        }

        fclose($handle);

        $results = [];
        while (!$heap->isEmpty()) {
            $results[] = $heap->extract();
        }
        $results = array_reverse($results);

        $handle = fopen($this->dataFile, 'rb');
        foreach ($results as &$result) {
            fseek($handle, $result['metadataOffset'], SEEK_SET);
            $metaDataJson = fread($handle, $result['metaDataLength']);
            $metadata = json_decode($metaDataJson, true);
            $result['metadata'] = $metadata;
            unset($result['metadataOffset'], $result['metaDataLength']);
        }
        fclose($handle);

        if ($metric === 'euclidean') {
            foreach ($results as &$result) {
                $result['distance'] = sqrt($result['distance']);
            }
        }

        return $results;
    }

    /**
     * Calculate the squared Euclidean distance between two vectors.
     * 
     * @param float[] $vec1
     * @param float[] $vec2
     * 
     * @return float
     */
    private function euclideanDistanceSquared(array $vec1, array $vec2): float
    {
        $sum = 0.0;
        for ($i = 1; $i <= $this->vectorDimension; $i++) {
            $diff = $vec1[$i - 1] - $vec2[$i];
            $sum += $diff * $diff;
        }
        
        return $sum;
    }

    /**
     * Calculate the cosine distance between two vectors.
     * 
     * @param float[] $vec1
     * @param float[] $vec2
     * 
     * @return float
     */
    private function cosineDistance(array $vec1, array $vec2): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        for ($i = 1; $i <= $this->vectorDimension; $i++) {
            $a = $vec1[$i - 1];
            $b = $vec2[$i];
            $dotProduct += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 1.0;
        }

        $cosineSimilarity = $dotProduct / (sqrt($normA) * sqrt($normB));

        return 1.0 - $cosineSimilarity;
    }
}
