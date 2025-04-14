<?php

class SillySearch {
    private $rootPath;
    private $siteInfo;
    private $results = [];
    private $searchQuery;

    public function __construct($rootPath, $searchQuery) {
        $this->rootPath = $rootPath;
        $this->searchQuery = $searchQuery;
        $this->siteInfo = $this->findSiteInfo($rootPath);
    }

    public function search() {
        $this->searchDirectory($this->rootPath);
        return [
            'site_info' => $this->siteInfo,
            'sillyco_branding' => 'Powered by NHK Creative\'s SillyCo.', 
            'query' => $this->searchQuery,
            'results' => $this->results
        ];
    }

    private function findSiteInfo($path) {
        while ($path !== '/') {
            $infoFile = $path . '/info.sillydata';
            if (file_exists($infoFile)) {
                return $this->parseSillyDataFile($infoFile, $path);
            }
            $path = dirname($path);
        }
        return null;
    }

    private function searchDirectory($dir, $parentData = null) {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $dataFile = $path . '/data.sillydata';
                if (file_exists($dataFile)) {
                    $data = $this->parseSillyDataFile($dataFile, $path);
                    if (!$this->isHidden($dataFile) && $this->matchesSearch($data)) {
                        $this->results[] = $data;
                    }
                    if (isset($data['subfolders']) && $data['subfolders'] === 'true') {
                        $this->searchDirectory($path, $data);
                    }
                }
            }
        }
    }

    private function parseSillyDataFile($file, $dirPath) {
        $content = file_get_contents($file);
        $lines = explode("\n", $content);
        $data = [];
        foreach ($lines as $line) {
            if (preg_match('/^(\&?)\*?:?([^=]+)=(.*)$/', trim($line), $matches)) {
                $key = $matches[2];
                $value = $matches[3];
                if ($matches[1] !== '&') {
                    if ($key === 'tags') {
                        $data[$key] = array_map('trim', explode(',', $value));
                    } else {
                        $data[$key] = $value;
                    }
                }
            }
        }
        
        // Auto-generate path if not present
        if (!isset($data['path'])) {
            $relativePath = str_replace($this->rootPath, '', $dirPath);
            // Ensure path starts with a forward slash and doesn't end with one (unless it's the root)
            $relativePath = '/' . ltrim($relativePath, '/');
            $relativePath = rtrim($relativePath, '/');
            if (empty($relativePath)) {
                $relativePath = '/';
            }
            $data['path'] = $relativePath;
        }
        
        return $data;
    }

    private function isHidden($dataFile) {
        $content = file_get_contents($dataFile);
        return preg_match('/^\&\*:hidden=true/m', $content) || preg_match('/^\*\&:hidden=true/m', $content);
    }

    private function matchesSearch($data) {
        if (empty($this->searchQuery)) {
            return true; // If no search query, include all silly results
        }
        
        $searchableFields = ['title', 'description', 'shortname'];
        foreach ($searchableFields as $field) {
            if (isset($data[$field]) && stripos($data[$field], $this->searchQuery) !== false) {
                return true;
            }
        }

        // Check tags
        if (isset($data['tags']) && is_array($data['tags'])) {
            foreach ($data['tags'] as $tag) {
                if (strcasecmp(trim($tag), trim($this->searchQuery)) === 0) {
                    return true;
                }
            }
        }

        return false;
    }
}

// Usage
$searchQuery = isset($_GET['q']) ? $_GET['q'] : '';
$searcher = new SillySearch(__DIR__, $searchQuery);
$results = $searcher->search();

header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);
?>
