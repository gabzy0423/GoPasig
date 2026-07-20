<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class ComparisonResult implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly float $lengthDifferenceKm,
        public readonly int $vertexDifference,
        public readonly float $boundingBoxOverlapPercent,
        public readonly float $hausdorffDistanceMeters,
        public readonly bool $advancedAnalysisPerformed = false,
        public readonly ?float $frechetSimilarityPercent = null,
    ) {}

    public function toArray(): array
    {
        return [
            'length_difference_km' => $this->lengthDifferenceKm,
            'vertex_difference' => $this->vertexDifference,
            'bounding_box_overlap_percent' => $this->boundingBoxOverlapPercent,
            'hausdorff_distance_meters' => $this->hausdorffDistanceMeters,
            'advanced_analysis_performed' => $this->advancedAnalysisPerformed,
            'frechet_similarity_percent' => $this->frechetSimilarityPercent,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
