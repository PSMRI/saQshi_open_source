<?php

require_once __DIR__ . '/ConfigLoader.php';

class FrameworkEngine
{
    private array $framework;

    public function __construct(string|array $framework)
    {
        if (is_string($framework)) {
            $this->framework = ConfigLoader::loadFramework($framework);
        } else {
            $this->framework = $framework;
        }

        $this->validateFramework();
    }

    public static function load(string $frameworkCode): self
    {
        return new self($frameworkCode);
    }

    public function toArray(): array
    {
        return $this->framework;
    }

    public function getFacilityTypes(): array
    {
        return $this->framework;
    }

    public function getFacilityTypeById(int $facTypeId): ?array
    {
        foreach ($this->framework as $facilityType) {
            if ((int)($facilityType['fac_type_id'] ?? 0) === $facTypeId) {
                return $facilityType;
            }
        }

        return null;
    }

    public function getDepartments(int $facTypeId): array
    {
        $facilityType = $this->getFacilityTypeById($facTypeId);

        if (!$facilityType) {
            return [];
        }

        return $facilityType['departments'] ?? [];
    }

    public function getDepartmentById(int $facTypeId, int $deptId): ?array
    {
        foreach ($this->getDepartments($facTypeId) as $department) {
            if ((int)($department['fac_dept_id'] ?? 0) === $deptId) {
                return $department;
            }
        }

        return null;
    }

    public function getConcerns(int $facTypeId, int $deptId): array
    {
        $department = $this->getDepartmentById($facTypeId, $deptId);

        if (!$department) {
            return [];
        }

        return $department['concerns'] ?? [];
    }

    public function getConcernById(
        int $facTypeId,
        int $deptId,
        int $concernId
    ): ?array {
        foreach ($this->getConcerns($facTypeId, $deptId) as $concern) {
            if ((int)($concern['concern_id'] ?? 0) === $concernId) {
                return $concern;
            }
        }

        return null;
    }

    public function getSubtypes(
        int $facTypeId,
        int $deptId,
        int $concernId
    ): array {
        $concern = $this->getConcernById(
            $facTypeId,
            $deptId,
            $concernId
        );

        if (!$concern) {
            return [];
        }

        return $concern['subtypes'] ?? [];
    }

    public function getSubtypeById(
        int $facTypeId,
        int $deptId,
        int $concernId,
        int $subtypeId
    ): ?array {
        foreach ($this->getSubtypes($facTypeId, $deptId, $concernId) as $subtype) {
            if ((int)($subtype['c_subtype_id'] ?? 0) === $subtypeId) {
                return $subtype;
            }
        }

        return null;
    }

    public function getCheckpoints(
        int $facTypeId,
        int $deptId,
        ?int $concernId = null,
        ?int $subtypeId = null
    ): array {
        $checkpoints = [];

        foreach ($this->getConcerns($facTypeId, $deptId) as $concern) {
            if ($concernId !== null && (int)$concern['concern_id'] !== $concernId) {
                continue;
            }

            foreach (($concern['subtypes'] ?? []) as $subtype) {
                if ($subtypeId !== null && (int)$subtype['c_subtype_id'] !== $subtypeId) {
                    continue;
                }

                foreach (($subtype['checkpoints'] ?? []) as $checkpoint) {
                    $checkpoint['_fac_type_id'] = $facTypeId;
                    $checkpoint['_fac_dept_id'] = $deptId;
                    $checkpoint['_concern_id'] = (int)($concern['concern_id'] ?? 0);
                    $checkpoint['_concern_name'] = $concern['concern_name'] ?? '';
                    $checkpoint['_concern_des'] = $concern['concern_des'] ?? '';
                    $checkpoint['_c_subtype_id'] = (int)($subtype['c_subtype_id'] ?? 0);
                    $checkpoint['_subtype_name'] = $subtype['area_of_con_subtypedeatils'] ?? '';
                    $checkpoint['_reference_no'] = $subtype['Reference_No'] ?? '';

                    $checkpoints[] = $checkpoint;
                }
            }
        }

        return $checkpoints;
    }

    public function getCheckpointById(int|string $checkpointId): ?array
    {
        foreach ($this->framework as $facilityType) {
            $facTypeId = (int)($facilityType['fac_type_id'] ?? 0);

            foreach (($facilityType['departments'] ?? []) as $department) {
                $deptId = (int)($department['fac_dept_id'] ?? 0);

                foreach (($department['concerns'] ?? []) as $concern) {
                    foreach (($concern['subtypes'] ?? []) as $subtype) {
                        foreach (($subtype['checkpoints'] ?? []) as $checkpoint) {
                            if ((string)($checkpoint['csqa_id'] ?? '') === (string)$checkpointId) {
                                $checkpoint['_fac_type_id'] = $facTypeId;
                                $checkpoint['_fac_dept_id'] = $deptId;
                                $checkpoint['_concern_id'] = (int)($concern['concern_id'] ?? 0);
                                $checkpoint['_c_subtype_id'] = (int)($subtype['c_subtype_id'] ?? 0);

                                return $checkpoint;
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    public function calculateScore(array $responses): array
    {
        $totalCheckpoints = 0;
        $filled = 0;
        $obtainedScore = 0;
        $maxScore = 0;

        foreach ($this->framework as $facilityType) {
            foreach (($facilityType['departments'] ?? []) as $department) {
                foreach (($department['concerns'] ?? []) as $concern) {
                    foreach (($concern['subtypes'] ?? []) as $subtype) {
                        foreach (($subtype['checkpoints'] ?? []) as $checkpoint) {
                            $totalCheckpoints++;

                            $checkpointId = (string)($checkpoint['csqa_id'] ?? '');

                            $max = $this->getMaxScore($checkpoint);
                            $maxScore += $max;

                            if (!array_key_exists($checkpointId, $responses)) {
                                continue;
                            }

                            $filled++;

                            $value = $responses[$checkpointId];
                            $obtainedScore += $this->resolveOptionScore($checkpoint, $value);
                        }
                    }
                }
            }
        }

        return [
            'total_checkpoints'  => $totalCheckpoints,
            'filled_checkpoints' => $filled,
            'max_score'          => $maxScore,
            'obtained_score'     => $obtainedScore,
            'percentage'         => $maxScore > 0
                ? round(($obtainedScore / $maxScore) * 100, 2)
                : 0
        ];
    }

    public function calculateScoreForScope(
        int $facTypeId,
        int $deptId,
        ?int $concernId,
        ?int $subtypeId,
        array $responses
    ): array {
        $checkpoints = $this->getCheckpoints(
            $facTypeId,
            $deptId,
            $concernId,
            $subtypeId
        );

        $total = count($checkpoints);
        $filled = 0;
        $obtained = 0;
        $maxScore = 0;

        foreach ($checkpoints as $checkpoint) {
            $checkpointId = (string)($checkpoint['csqa_id'] ?? '');

            $maxScore += $this->getMaxScore($checkpoint);

            if (!array_key_exists($checkpointId, $responses)) {
                continue;
            }

            $filled++;
            $obtained += $this->resolveOptionScore(
                $checkpoint,
                $responses[$checkpointId]
            );
        }

        return [
            'total_checkpoints'  => $total,
            'filled_checkpoints' => $filled,
            'max_score'          => $maxScore,
            'obtained_score'     => $obtained,
            'percentage'         => $maxScore > 0
                ? round(($obtained / $maxScore) * 100, 2)
                : 0
        ];
    }

    private function resolveOptionScore(array $checkpoint, mixed $value): float
    {
        $options = $checkpoint['response']['options'] ?? [];

        foreach ($options as $option) {
            if ((string)($option['value'] ?? '') === (string)$value) {
                return (float)($option['score'] ?? 0);
            }
        }

        return is_numeric($value) ? (float)$value : 0;
    }

    private function getMaxScore(array $checkpoint): float
    {
        $options = $checkpoint['response']['options'] ?? [];

        if (empty($options)) {
            return 0;
        }

        $scores = array_map(
            fn($option) => (float)($option['score'] ?? 0),
            $options
        );

        return max($scores);
    }

    private function validateFramework(): void
    {
        if (!is_array($this->framework)) {
            throw new Exception('Invalid framework config');
        }

        /*
     * Wrapped format:
     * {
     *   "framework": {},
     *   "settings": {},
     *   "facility_types": [
     *      {
     *        "fac_type_id": 1,
     *        "departments": []
     *      }
     *   ]
     * }
     */
        if (isset($this->framework['facility_types'])) {
            $this->framework = $this->framework['facility_types'];
        }

        /*
     * Alternative wrapped format:
     * {
     *   "data": [
     *      {
     *        "fac_type_id": 1,
     *        "departments": []
     *      }
     *   ]
     * }
     */
        if (isset($this->framework['data']) && is_array($this->framework['data'])) {
            $this->framework = $this->framework['data'];
        }

        foreach ($this->framework as $index => $facilityType) {

            if (!is_array($facilityType)) {
                throw new Exception('Invalid framework config at index ' . $index);
            }

            if (!isset($facilityType['fac_type_id']) && isset($facilityType['id'])) {
                $this->framework[$index]['fac_type_id'] = (int)$facilityType['id'];
            }

            if (!isset($this->framework[$index]['fac_type_id'])) {
                throw new Exception(
                    'Invalid framework config: fac_type_id missing at index ' . $index
                );
            }

            if (
                !isset($facilityType['departments']) ||
                !is_array($facilityType['departments'])
            ) {
                throw new Exception(
                    'Invalid framework config: departments missing at fac_type_id ' .
                        $facilityType['fac_type_id']
                );
            }
        }
    }
}
