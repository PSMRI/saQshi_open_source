<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Deployment Configuration Service
 * DeploymentConfigService.php
 * Version 1.0.0 | Updated 2026-07-18
 * ==========================================================
 */

class DeploymentConfigService
{
    public static function current(): array
    {
        $domain = self::readJson(__DIR__ . '/../config/domain.json', self::defaultDomain());
        $modules = self::readJson(__DIR__ . '/../config/modules.json', self::defaultModules());

        return [
            'domain' => $domain,
            'modules' => self::normalizeModules($modules),
            'profiles' => self::profiles()
        ];
    }

    public static function applyProfile(string $profileCode, int $userId): array
    {
        $profileCode = preg_replace('/[^a-z0-9_-]/i', '', trim($profileCode));

        if ($profileCode === '') {
            throw new InvalidArgumentException('Profile code is required.');
        }

        $profilePath = __DIR__ . '/../config/profiles/' . $profileCode . '.json';
        $profile = self::readJson($profilePath, []);

        if (!$profile) {
            throw new RuntimeException('Deployment profile not found.');
        }

        $domain = [
            'domain' => (string)($profile['profile_code'] ?? $profileCode),
            'profile_code' => (string)($profile['profile_code'] ?? $profileCode),
            'profile_name' => (string)($profile['profile_name'] ?? $profileCode),
            'default_framework' => (string)($profile['default_framework'] ?? ''),
            'labels' => array_replace(
                self::defaultDomain()['labels'],
                is_array($profile['labels'] ?? null) ? $profile['labels'] : []
            ),
            'applied_by' => $userId,
            'applied_on' => date('c')
        ];

        $currentModules = self::normalizeModules(
            self::readJson(__DIR__ . '/../config/modules.json', self::defaultModules())
        );
        $profileModules = is_array($profile['modules'] ?? null) ? $profile['modules'] : [];

        foreach ($profileModules as $key => $enabled) {
            if (!isset($currentModules['modules'][$key])) {
                $currentModules['modules'][$key] = [
                    'enabled' => (bool)$enabled,
                    'label' => ucwords(str_replace('_', ' ', (string)$key))
                ];
                continue;
            }

            $currentModules['modules'][$key]['enabled'] = (bool)$enabled;
        }

        $enabledKeys = array_values(array_filter(
            array_keys($currentModules['modules']),
            fn($key) => !empty($currentModules['modules'][$key]['enabled'])
        ));
        $currentModules['domain'] = (string)($profile['profile_code'] ?? $profileCode);
        $currentModules['active_profile'] = (string)($profile['profile_code'] ?? $profileCode);
        $currentModules['default_framework'] = (string)($profile['default_framework'] ?? '');
        $currentModules['role_visibility'] = [
            'assessor' => in_array('assessment', $enabledKeys, true) ? ['assessment'] : [],
            'facility' => $enabledKeys,
            'state' => $enabledKeys
        ];

        self::writeJson(__DIR__ . '/../config/domain.json', $domain);
        self::writeJson(__DIR__ . '/../config/modules.json', $currentModules);

        return self::current();
    }

    private static function profiles(): array
    {
        $dir = __DIR__ . '/../config/profiles';
        $profiles = [];

        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $data = self::readJson($file, []);

            if ($data) {
                $profiles[] = [
                    'profile_code' => (string)($data['profile_code'] ?? basename($file, '.json')),
                    'profile_name' => (string)($data['profile_name'] ?? basename($file, '.json')),
                    'recommended_for' => (string)($data['recommended_for'] ?? ''),
                    'default_framework' => (string)($data['default_framework'] ?? ''),
                    'modules' => $data['modules'] ?? [],
                    'labels' => $data['labels'] ?? []
                ];
            }
        }

        return $profiles;
    }

    private static function normalizeModules(array $config): array
    {
        $default = self::defaultModules();
        $config['modules'] = array_replace_recursive($default['modules'], $config['modules'] ?? []);
        $config['role_visibility'] = array_replace_recursive($default['role_visibility'], $config['role_visibility'] ?? []);
        $config['domain'] = (string)($config['domain'] ?? $default['domain']);

        return $config;
    }

    private static function readJson(string $path, array $fallback): array
    {
        if (!is_file($path)) {
            return $fallback;
        }

        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : $fallback;
    }

    private static function writeJson(string $path, array $data): void
    {
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write deployment configuration.');
        }
    }

    private static function defaultDomain(): array
    {
        return [
            'domain' => 'healthcare',
            'profile_code' => 'healthcare',
            'labels' => [
                'facility' => 'Facility',
                'facilities' => 'Facilities',
                'facility_user' => 'Facility User',
                'facility_code' => 'NIN',
                'assessor' => 'Assessor',
                'department' => 'Department',
                'departments' => 'Departments',
                'assessment' => 'Assessment',
                'assessments' => 'Assessments',
                'checkpoint' => 'Checkpoint',
                'checkpoints' => 'Checkpoints',
                'checklist' => 'Checklist',
                'facility_profile' => 'Facility Profile',
                'assessor_dashboard' => 'Assessor Dashboard',
                'assigned_facilities' => 'Assigned Facilities',
                'evidence' => 'Evidence'
            ]
        ];
    }

    private static function defaultModules(): array
    {
        return [
            'domain' => 'healthcare',
            'modules' => [
                'assessment' => ['enabled' => true, 'label' => 'Assessment'],
                'cqi' => ['enabled' => true, 'label' => 'CQI / Gap Closure'],
                'performance' => ['enabled' => true, 'label' => 'Performance Monitoring'],
                'kpi' => ['enabled' => true, 'label' => 'KPI'],
                'outcome' => ['enabled' => true, 'label' => 'Outcome'],
                'certification' => ['enabled' => true, 'label' => 'Certification'],
                'reports' => ['enabled' => true, 'label' => 'Reports'],
                'field_analytics' => ['enabled' => true, 'label' => 'Field Analytics'],
                'map' => ['enabled' => true, 'label' => 'Map']
            ],
            'role_visibility' => [
                'assessor' => ['assessment'],
                'facility' => ['assessment', 'cqi', 'performance', 'kpi', 'outcome', 'certification', 'reports'],
                'state' => ['assessment', 'cqi', 'performance', 'kpi', 'outcome', 'certification', 'reports', 'field_analytics', 'map']
            ]
        ];
    }
}
