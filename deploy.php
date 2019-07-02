<?php
/** ***
 * Get the template page for folders */

$templateFolderPage = file_get_contents('./_templates/folder.html');
unlink('./_templates/folder.html');

/** ***
 * Get valid folders in the root directory */

// Filters out folders starting with "_" or "."
$rootFolders = array_filter(glob('*', GLOB_ONLYDIR), function($str) {
	return $str[0] != '_' && $str[0] != '.' && !file_exists($str . '/.archived');
});

$allFiles = array(
	'folders' => $rootFolders ?: [],
	'files' => []);


/** ***
 * Generate infos for the root directory */

// Returns meta.json files informations
function getMetaInfos($pathList) {
	if(empty($pathList)) return [];
	
	$metaInfos = [];
	$i = 0;
	foreach($pathList as $path) {
		$isDir = is_dir($path);
		$dir = $isDir ? $path : dirname($path);
		$metaInfos[$i] = [];
		
		if($isDir) {
			// Check if no subfiles in this folder
			$subfiles = glob($path . '/*.*');
			if(empty($subfiles)) {
				// List subfolders
				$subfolders = glob($path . '/*', GLOB_ONLYDIR) ?: [];
				$metaInfos[$i]['subfolders'] = [];
				foreach($subfolders as $folder) {
					$metaInfos[$i]['subfolders'][] = array(
						'basename' => basename($folder),
						'path' =>str_replace('#', '%23', $folder)
					);
				}
			}
			
			// Generate breadcrumb for this folder
			$breadcrumb = [];
			foreach(explode('/', $dir) as $each)
				$breadcrumb[] = $each;
			
			// Generate index file for this folder
			$search = ['##DIRNAME##', '##TITLE##', '##DESCRIPTION##', '##BREADCRUMB##', '##CONTENT##', '##DATAPATH##'];
			$replace = [
				basename($dir),
				isset($metaJSONs[$dir]['folder']['title']) ? $metaJSONs[$dir]['folder']['title'] : '',
				isset($metaJSONs[$dir]['folder']['description']) ? $metaJSONs[$dir]['folder']['description'] : '',
				'- Home' . "\n" . '- ' . implode("\n" . '- ', $breadcrumb),
				file_exists($dir . '/README.md') ? '{% include_relative README.md %}' : '',
				str_replace('#', 's', strtolower(implode('.', $breadcrumb)))
			];
			
			// Save generated index file
			global $templateFolderPage;
			$thisPage = str_replace($search, $replace, $templateFolderPage);
			file_put_contents($dir . '/index.html', $thisPage);
		}
		else {
			$fileInfos = pathinfo($path);
			if(isset($metaJSONs[$dir]['files'][$fileInfos['basename']]))
				$metaInfos[$i] = $metaJSONs[$dir]['files'][$fileInfos['basename']];
			if(!isset($metaInfos[$i]['embed']) AND $fileInfos['extension'] == 'pdf')
				$metaInfos[$i]['embed'] = true;
		}
		
		$metaInfos[$i]['basename'] = basename($path);
		$metaInfos[$i]['path'] = str_replace('#', '%23', $path); // URL encodes '#' characters
		$i++;
	}
	return $metaInfos;
}

// Generate JSON for root directory's data file
$mainInfos = array('folders' => getMetaInfos($rootFolders), 'files' => []);

// Make _data/ folder if it doesn't exist already
if(!file_exists('_data/')) mkdir('_data/');

// Save data file
$handle = fopen('_data/subitems.json', 'w');
fwrite($handle, json_encode($mainInfos, JSON_UNESCAPED_SLASHES));
fclose($handle);


/** ***
 * Build all valid sub-folders in the root directory */

// Recursively build all (valid) folders from root directory
function buildSubFolders($folders) {
	if(empty($folders)) return;
	foreach($folders as $folder) {
		$glob = array(
			'folders' => glob($folder . '/*', GLOB_ONLYDIR) ?: [],
			'files' => array_filter(glob($folder . '/*.*') ?: [], function($file) {
				// Filters out web pages
				$extension = pathinfo($file, PATHINFO_EXTENSION);
				return $extension != 'html';
			}));
		
		// Generate JSON data file
		$folderInfos = array(
			'folders' => getMetaInfos($glob['folders']),
			'files' => getMetaInfos($glob['files'])); // No process yet for files.
		
		// Make new folders
		$lowercaseFolder = str_replace('#', 's', strtolower($folder));
		if(!file_exists('_data/' . $lowercaseFolder . '/')) mkdir('_data/' . $lowercaseFolder . '/');
		
		// Save data file
		$handle = fopen('_data/' . $lowercaseFolder . '/subitems.json', 'w');
		fwrite($handle, json_encode($folderInfos, JSON_UNESCAPED_SLASHES));
		fclose($handle);
		
		// Recursivity
		buildSubFolders($glob['folders']);
	}
}
buildSubFolders($rootFolders);


/** ***
 * Move files from _assets/ to assets/ */

rename('./_assets/', './assets/');
?>