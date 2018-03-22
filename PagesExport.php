<?php
//<?php namespace ProcessWire;

/**
 * ProcessWire Pages Export/Import Helpers
 * 
 * This class is in development and not yet ready for use. 
 * 
 * $options argument for import methods:
 * 
 *  - `commit` (bool): Commit/save the changes now? (default=true). Specify false to perform a test import.
 *  - `update` (bool): Allow update of existing pages? (default=true)
 *  - `create` (bool): Allow creation of new pages? (default=true)
 *  - `parent` (Page|string|int): Parent Page, path or ID. Omit to use import data (default=0).
 *  - `template` (Template|string|int): Template object, name or ID. Omit to use import data (default=0).
 *  - `fieldNames` (array): Import only these field names, or omit to use all import data (default=[]).
 *  - `changeStatus` (bool): Allow status to be changed aon existing pages? (default=true)
 *  - `changeSort` (bool): Allow sort and sortfield to be changed on existing pages? (default=true)
 * 
 * Note: all the "change" prefix options require update=true. 
 * 
 * ProcessWire 3.x, Copyright 2017 by Ryan Cramer
 * https://processwire.com
 *
 * @author Ryan Cramer
 * @author Francis Otieno (Kongondo)
 * Amended for EXPORT ONLY for ProcessWire sites using version < 2.8 (especially 2.7)
 * The idea is to use the class to export pages to later import to ProcessWire 3.x
 * Import is to be done using ProcessWire 3.x PagesExportImport class or ProcessPagesExportImport
 * This amended class ensures the export will be compatible with the intended import
 * The class also includes necessary methods from ProcessWire 3.x Fieldtype.php, Functions.php and WireFileTools.php
 * 
 */

class PagesExport extends Wire {

	/*
		@note:
		- Tested and works in ProcessWire 2.2 master - 2.7.3 dev (both PagesExport and ProcessPagesExport)
	*/

	// @kongondo
	private $versionMinor;// for pw minor version

	/**
	 * Set up. @kongondo
	 *
	 * @access public
	 *
	 */
    public function __construct() {
		// if user not superuser, exit;
		if(!$this->wire('user')->isSuperuser()) exit;		
		// we only support ProcessWire 2.2; 2.3; 2.4; 2.5; 2.6 and 2.7
		$this->compatibilityCheck();
		// if invalid PW version, exit;
		if($this->versionMinor == 0) {
			/* echo 'ProcessWire version not compatible with this tool.';
			exit; */
			throw new WireException('ProcessWire version not compatible with this tool.');			
		}		
	}
	
	/**
	 * For debugging __call() only.
	 *
	 * @kongondo: @note: 
	 * 
	 * @access private
	 * @param string $name Name of the method.
	 * @param array $arguments Method arguments.
	 * @param integer $mode Whether to exit (2) or not (1).
	 * 
	 */
	private function callOutput($name, $arguments, $mode=1) {
		echo '<pre>';
		print_r($arguments);
		echo '</pre>';
        // Note: value of $name is case sensitive.
        echo "Calling object method '$name' "
			 . implode(', ', $arguments). "\n";
		//echo $name . '<br>';
		if($mode == 2 ) exit;

	}

	/**
	 * Fallback for older ProcessWire versions (< 2.4) without $this->wire().
	 * 	 
	 * We use PHP magic method __call() to fallback to wire().
	 *
	 * @access public
	 * @param string $name Name of the method.
	 * @param array $arguments Method arguments.
	 * 
	 */
	public function __call($name, $arguments) {
		if($name == 'wire') return wire($arguments[0]);
    }

	/**
	 * Check if the ProcessWire version this script is running in is supported.
	 *
	 * We only support ProcessWire 2.2 - 2.7 inclusive.
	 * 
	 * @access public
	 * @return integer $versionMinor The minor version of the installed ProcessWire.
	 * 
	 */
	public function compatibilityCheck() {
		// we only support ProcessWire 2.2; 2.3; 2.4; 2.5; 2.6 and 2.7
		$versionParts = $this->getVersionParts();
		// if versionMajor < 3 (i.e. in PW 2.x) we store the versionMinor (.x)
		$major = (int) $versionParts[0];
		$minor = (int) $versionParts[1];		
		$this->versionMinor = $versionMinor = $major < 3 && $minor > 1 ? $minor : 0;
		return $versionMinor;

	}

	/**
	 * Gateway method for export types.
	 * 
	 * These are JSON, Array and Zip.
	 *
	 * @access public
	 * @param PageArray $items Items to export.
	 * @param array $options Options to shape export (e.g exclude certain fields).
	 * @return string|array|null $error|$data.
	 * 
	 */
    public function export(PageArray $items, array $options = array()) {

        $data = '';
        $error = '';
        
        if(!isset($options['mode'])) {
            $error = "You need to set a mode for the export(array/json/zip)";
            return $error;     
        }

        $mode = $options['mode'];

        if(!in_array($mode, array('array','json','zip'))) {
            $error = "Unknown mode! Mode can only be either of 'array/json/zip'";
            return $error;
		}		

        if(!$items->count()) {
            $error = "No items found to export!";
            return $error;
        }

        // good to go

        if('array' == $mode) $data = $this->pagesToArray($items, $options);
        elseif('json' == $mode) $data = $this->exportJSON($items, $options);
        elseif('zip' == $mode) $data = $this->exportZIP($items, $options);

        return $data;

	}
	
	/**
	 * Get ProcessWire versionMajor, versionMinor and versionRevision.
	 *
	 * @access private
	 * @return array $versionParts Array with ProcessWire version parts. 
	 * 
	 */
	public function getVersionParts() {
		$versionParts = explode('.', $this->wire('config')->version);
		return $versionParts;
	}

	/**
	 * Return the web accessible URL (with scheme and hostname) to this Pagefile.
	 *
	 * Code borrwed from Pagefile::___httpUrl()
	 * For ProcessWire version < 2.4
	 * 
	 * @access private
	 * @param Page $page The page containing the pagefile.
	 * @param Pagefile $pagefile The pagefile whose url to build.
	 * @return String $url The url to the pagefile.
	 * 
	 */
	private function getHttpUrl($page, $pagefile) {		
		if($this->versionMinor < 4) {
			$url = substr($page->httpUrl(), 0, -1 * strlen($page->url())) . $pagefile->url;
			//$url = $url . $pagefile->url . $pagefile->basename;// verbose
		}
		else $url = $pagefile->httpUrl();		
		return $url;// @kongondo @todo: test some more!
	}

	/**
	 * Determine the export value of a given field.
	 *
	 * @access private
	 * @param Page $page Page containing the export field.
	 * @param Field $field The field whose value to export.
	 * @param string|integer|array|object $value The value to be exported.
	 * @param array $exportValueOptions Options to shape the export.
	 * @return void
	 * 
	 */
	private function getExportValue($page, $field, $value, $exportValueOptions) {
		/* @kongondo: @note:
			- the output of exportValue() of PW 2.5-2.7 is slightly different from that of PW 3.x
			- PW < 2.4 fields don't have exportValues
			- hence, we use the PW 3.x's throughout
		*/

		if($field->type instanceof FieldtypeRepeater && $value) {
			$exportValue = $this->exportValueRepeater($page, $field, $value, $exportValueOptions);
		}
		elseif(($value instanceof Pageimage) || ($value instanceof Pageimages)) {
			$exportValue = $this->exportValueImage($page, $field, $value, $exportValueOptions);
		}
		elseif(($value instanceof Pagefile) || ($value instanceof Pagefiles)) {
			$exportValue = $this->exportValueFile($page, $field, $value, $exportValueOptions);
		}
		elseif(($value instanceof Page) || ($value instanceof PageArray)) {
			$exportValue = $this->exportValuePages($page, $field, $value, $exportValueOptions);
		}
		else $exportValue = $field->type->sleepValue($page, $field, $value);

		return $exportValue;
	}



    /********************** FROM ProcessWire 3.x: /wire/core/PagesExportImport.php ***************************/

    /**
	 * Get the path where ZIP exports are stored
	 * 
	 * @access public
	 * @param string $subdir Specify a subdirectory name if you want it to create it. 
	 *   If it exists, it will create a numbered version of the subdir to ensure it is unique. 
	 * @return string
	 * 
	 */
	public function getExportPath($subdir = '') {
	
		/** @var WireFileTools $files */
		//$files = $this->wire('files');// @kongondo
		$path = $this->wire('config')->paths->assets . 'backups/' . $this->className() . '/';
		
		$readmeText = "When this file is present, files and directories in here are auto-deleted after a short period of time.";
		$readmeFile = $this->className() . '.txt';
		$readmeFiles = array();
		
		if(!is_dir($path)) {
			#$files->mkdir($path, true);
			$this->mkdir($path, true);// @kongondo
			$readmeFiles[] = $path . $readmeFile;
		}
		
		if($subdir) {
			$n = 0;
			do {
				$_path = $path . $subdir . ($n ? "-$n" : '') . '/';
			} while(++$n && is_dir($_path)); 
			$path = $_path;
			#$files->mkdir($path, true);
			$this->mkdir($path, true);// @kongondo
			$readmeFiles[] = $path . $readmeFile;
		}
		
		foreach($readmeFiles as $file) {
			file_put_contents($file, $readmeText);
            #$files->chmod($readmeFile);// @kongondo
            $this->chmod($readmeFile); // @kongondo
		}
		
		return $path; 
	}

	/**
	 * Remove files and directories in /site/assets/backups/PagesExportImport/ that are older than $maxAge
	 * 
	 * @access public
	 * @param int $maxAge Maximum age in seconds
	 * @return int Number of files/dirs removed
	 * 
	 */
	public function cleanupFiles($maxAge = 3600) {

		/** @var WireFileTools $files */
		$files = $this->wire('files');//
		$path = $this->getExportPath();
		$qty = 0;
		
		foreach(new \DirectoryIterator($path) as $file) {
			
			if($file->isDot()) continue;
			if($file->getBasename() == $this->className() . '.txt') continue; // we want this file to stay
			if($file->getMTime() >= (time() - $maxAge)) continue; // not expired
			
			$pathname = $file->getPathname();
			
			if($file->isDir()) {
				$testFile = rtrim($pathname, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->className() . '.txt';
				if(!is_file($testFile)) continue; 
				if($files->rmdir($pathname, true)) {
					$this->message($this->_('Removed old directory') . " - $pathname", Notice::debug); 
					$qty++;
				}
			} else {
				if(unlink($pathname)) {
					$this->message($this->_('Removed old file') . " - $pathname", Notice::debug); 
					$qty++;
				}
			}
		}
		
		return $qty; 
	}

	/**
	 * Export given PageArray to a ZIP file
	 * 
	 * @access public
	 * @param PageArray $items
	 * @param array $options
	 * @return string|bool Path+filename to ZIP file or boolean false on failure
	 * 
	 */
	public function exportZIP(PageArray $items, array $options = array()) {
		
		/** @var WireFileTools $files */
        #$files = $this->wire('files');// @kongondo       
		
        $options['exportTarget'] = 'zip';        

		$zipPath = $this->getExportPath();
		//if(!is_dir($zipPath)) $files->mkdir($zipPath, true);// @kongondo
		if(!is_dir($zipPath)) $this->mkdir($zipPath, true);// @kongondo
		
		#$tempDir = new WireTempDir($this);// @kongondo
		// @kongondo: @note: we use imported methods if in PW 2.4 or less
		if($this->versionMinor < 5) {
			$tempDir = $this->create($this);// @kongondo
			$tmpPath = $this->getTempDir();// @kongondo
		}
		else {
			$tempDir = new WireTempDir($this);// @kongondo
			$tmpPath = $tempDir->get();// @kongondo
		}
		$this->wire($tempDir);// @kongondo
		//$tmpPath = $tempDir->get();// @kongondo
		$jsonFile = $tmpPath . "pages.json";
		$zipItems = array($jsonFile);
		$data = $this->pagesToArray($items, $options);
	
		// determine other files to add to ZIP
		foreach($data['pages'] as $key => $item) {
			if(!isset($item['_filesPath'])) continue;
			$zipItems[] = $item['_filesPath'];
			unset($data['pages'][$key]['_filesPath']);
		}
	
		// write out the pages.json file
		file_put_contents($jsonFile, wireEncodeJSON($data, true, true));

		$n = 0;
		do {
			$zipName = $zipPath . 'pages' . ($n ? "-$n" : '') . '.zip';
		} while(++$n && file_exists($zipName)); 
		
		// @todo report errors from zipInfo
		#$zipInfo = $files->zip($zipName, $zipItems, array(// @kongondo
		$zipInfo = $this->zip($zipName, $zipItems, array(// @kongondo
			'maxDepth' => 1, 
			'allowHidden' => false, 
			'allowEmptyDirs' => false
		)); 
		
		unlink($jsonFile); 
		
		return $zipName;
	}

	/**
	 * Export a PageArray to JSON string
	 * 
	 * @access public
	 * @param PageArray $items
	 * @param array $options
	 * @return string|bool JSON string of pages or boolean false on error
	 * 
	 */
	public function exportJSON(PageArray $items, array $options = array()) {
		$defaults = array(
			'exportTarget' => 'json'
		);
		$options = array_merge($defaults, $options); 
		$data = $this->pagesToArray($items, $options); 
		$data = wireEncodeJSON($data, true, true);
		return $data;
	}
	
	/**
	 * Given a PageArray export it to a portable PHP array
	 *
	 * @access public
	 * @param PageArray $items
	 * @param array $options Additional options to modify behavior
	 * @return array
	 *
	 */
	public function pagesToArray(PageArray $items, array $options = array()) {

		

		$defaults = array(
			'verbose' => false,
            'fieldNames' => array(), // export only these field names, when specified
            // @kongondo
            'fieldNamesExclude' => array(), // exclude these field names from export, when specified
		);

		$options = array_merge($defaults, $options);
		$options['verbose'] = false; // TMP option not yet supported
		// @kongondo: @note: even when 'verbose' supported, we can't use it in < PW 2.5

		$a = array(
			'type' => 'ProcessWire:PageArray',
			'created' => date('Y-m-d H:i:s'), 
			'version' => $this->wire('config')->version,			
			'user' => $this->wire('user')->name,
			'host' => $this->wire('config')->httpHost,
			'pages' => array(),
			'fields' => array(),
			'timer' => Debug::timer(), 
			// 'pagination' => array(),
		);
		
		if($items->getLimit()) {
			$pageNum = $this->wire('input')->pageNum;
			$a['pagination'] = array(
				'start' => $items->getStart(),
				'limit' => $items->getLimit(),
				'total' => $items->getTotal(),
				'this' => $pageNum, 
				'next' => ($items->getTotal() > $items->getStart() + $items->count() ? $pageNum+1 : false), 
				'prev' => ($pageNum > 1 ? $pageNum - 1 : false)
			);
		} else {
			unset($a['pagination']);
		}

		/** @var Languages $languages */
		$languages = $this->wire('languages');
		if($languages) $languages->setDefault();
		$templates = array();

		foreach($items as $item) {

			$exportItem = $this->pageToArray($item, $options);
			$a['pages'][$exportItem['path']] = $exportItem;

			// include information about field settings so that warnings can be generated at
			// import time if there are applicable differences in the field settings
			foreach($exportItem['data'] as $fieldName => $value) {
				$fieldNames = array($fieldName);
				if(is_array($value) && !empty($value['type']) && $value['type'] == 'ProcessWire:PageArray') {
					// nested PageArray, pull in fields from it as well
					foreach(array_keys($value['fields']) as $fieldName) $fieldNames[] = $fieldName;
				}
				foreach($fieldNames as $fieldName) {
					if(isset($a['fields'][$fieldName])) continue;
					$field = $this->wire('fields')->get($fieldName);
					if(!$field || !$field->type) continue;


					// @kongondo: 
					// @note: PW version < 2.5 does not implement Modules::getModuleInfoVerbose
					// so, we fall back to Modules::getModuleInfo in older versions
					// older PW version (< 2.5)
					if($this->versionMinor < 5) {
						$moduleInfo = $this->wire('modules')->getModuleInfo($field->type);
						$moduleVersionStr = $moduleInfo['version'];
					}
					// newer PW version (> 2.4)
					else {
						$moduleInfo = $this->wire('modules')->getModuleInfoVerbose($field->type);
						$moduleVersionStr = $moduleInfo['versionStr'];
					}					
					
					############################
					
					
					if($options['verbose']) {
						$fieldData = $field->getExportData();
						unset($fieldData['name']);
						$a['fields'][$fieldName] = $fieldData;
					} else {
						$a['fields'][$fieldName] = array(
							'type' => $field->type->className(),
							'label' => $field->label,
							//'version' => $moduleInfo['versionStr'],
							// @kongondo
							'version' => $moduleVersionStr,
							'id' => $field->id
						);
					}
					
					$blankValue = $field->type->getBlankValue($item, $field);
					
					if(is_object($blankValue)) {
						if($blankValue instanceof Wire) {
							$blankValue = "class:" . $blankValue->className();
						} else {
							$blankValue = "class:" . get_class($blankValue);
						}
					}

					
					$a['fields'][$fieldName]['blankValue'] = $blankValue;
					
					//foreach($field->type->getImportValueOptions($field) as $k => $v) {// @kongondo
					$fieldGetImportValueOptions = $this->getImportValueOptions($field);// @kongondo
					foreach($fieldGetImportValueOptions as $k => $v) {
						if(isset($a['fields'][$fieldName][$k])) continue;
						$a['fields'][$fieldName][$k] = $v;
					}
				}
			}

			// include information about template settings so that warnings can be generated
			// at import time if there are applicable differences in the template settings
			if($options['verbose']) {
				if(!isset($templates[$item->template->name])) {
					$templates[$item->template->name] = $item->template->getExportData();
				}
			}
			
		}
	
		// sort by path to ensure parents are created before their children
		ksort($a['pages']); 
		$a['pages'] = array_values($a['pages']); 
		$a['timer'] = Debug::timer($a['timer']); 		

		if($options['verbose']) $a['templates'] = $templates;

		if($languages) $languages->unsetDefault();

		return $a;
	}
	
	/**
	 * Export Page object to an array
	 *
	 * @access protected
	 * @param Page $page
	 * @param array $options
	 * @return array
	 *
	 */
	protected function pageToArray(Page $page, array $options) {
		
		$defaults = array(
			'exportTarget' => '',
		);
		$options = array_merge($defaults, $options); 
		
		$of = $page->of();
		$page->of(false);

		

		/** @var Languages $languages */
		$languages = $this->wire('languages');
		if($languages) $languages->setDefault();
		$numFiles = 0;		
	
		// standard page settings
		$settings = array(
			'id' => $page->id, // for connection to exported file directories only
			'name' => $page->name,
			'status' => $page->status,
			'sort' => $page->sort,
			'sortfield' => $page->sortfield,
		);

		// verbose page settings
		if(!empty($options['verbose'])) {
			$settings = array_merge($settings, array(
				'parent_id' => $page->parent_id,
				'templates_id' => $page->templates_id,
				'created' => $page->createdStr,
				'modified' => $page->modifiedStr,
				'published' => $page->publishedStr,
				'created_user' => $page->createdUser->name,
				'modified_user' => $page->modifiedUser->name,
			));
		}
		
		// include multi-language page names and statuses when applicable
		if($languages && $this->wire('modules')->isInstalled('LanguageSupportPageNames')) {
			foreach($languages as $language) {
				if($language->isDefault()) continue;
				$settings["name_$language->name"] = $page->get("name$language->id");
				$settings["status_$language->name"] = $page->get("status$language->id");
			}
		}

		// array of export data
		$a = array(
			'type' => 'ProcessWire:Page',
			'path' => $page->path(),
			//'class' => $page->className(true), 
			'class' => "ProcessWire\\" . $page->className(),// @kongondo: append 'PrcessWire' namespace to enable import
			'template' => $page->template->name,
			'settings' => $settings, 
			'data' => array(),
			// 'warnings' => array(),
		);
		
		$exportValueOptions = array(
			'system' => true, 
			'caller' => $this, 
			'FieldtypeFile' => array(
				'noJSON' => true
			),
			'FieldtypeImage' => array(
				'variations' => true, 
			),				
		);		
	
		// iterate all fields and export value from each
		foreach($page->template->fieldgroup as $field) {
            /** @var Field $field */
			
            if(!empty($options['fieldNames']) && !in_array($field->name, $options['fieldNames'])) continue;
            // @kongondo: exclude these fields
            if(!empty($options['fieldNamesExclude']) && in_array($field->name, $options['fieldNamesExclude'])) continue;
            
			$info = $this->getFieldInfo($field); 
			if(!$info['exportable']) continue;			
		          
            $value = $page->getUnformatted($field->name);
			
			// @kongondo
			$exportValue = $this->getExportValue($page, $field, $value, $exportValueOptions);

			$a['data'][$field->name] = $exportValue;
			
			if($field->type instanceof FieldtypeFile && $value) {
                $numFiles += count($value);
			}
		}
		
		// @kongondo @note: this includes 
		if($numFiles && $options['exportTarget'] == 'zip') {
            $a['_filesPath'] = $page->filesManager()->path();
		}

		

		if($of) $page->of(true);
		
		if($languages) $languages->unsetDefault();

	
		return $a;
	}

	/**
	 * Returns array of information about given Field
	 * 
	 * Populates the following indexes: 
	 *  - `exportable` (bool): True if field is exportable, false if not. 
	 *  - `reason` (string): Reason why field is not exportable (when exportable==false). 
	 * 
	 * @access public
	 * @param Field $field
	 * @return array
	 * 
	 */
	public function getFieldInfo(Field $field) {
		
		static $cache = array();
		
		if(isset($cache[$field->id])) return $cache[$field->id];
		
		$fieldtype = $field->type;
		$exportable = true;
		$reason = '';
		
		$extraType = $this->wireInstanceOf($fieldtype, array(
			'FieldtypeFile',
			'FieldtypeRepeater',
			'FieldtypeComments',
		));
		
		if($extraType) {
			// extra identified types are allowed
			
		} else if($fieldtype instanceof FieldtypeFieldsetOpen || $fieldtype instanceof FieldtypeFieldsetClose) {
			// fieldsets not exportable
			$reason = 'Nothing to export/import for fieldsets';
			$exportable = false;
			
		} else {
			// test to see if exportable
			try {
                //$importInfo = $fieldtype->getImportValueOptions($field); // @kongondo
                $importInfo = $this->getImportValueOptions($field);// @kongondo
			} catch(\Exception $e) {
				$exportable = false;
				$reason = $e->getMessage();
			}

			if($exportable && !$importInfo['importable']) {
				// this fieldtype is storing data outside of the DB or in other unknown tables
				// there's a good chance we won't be able to export/import this into an array
				// @todo check if fieldtype implements its own exportValue/importValue, and if
				// it does then allow the value to be exported
				$exportable = false;
				$reason = "Field '$field' cannot be used because $field->type indicates imports are not supported";
			}
		}
		
		if(!$exportable && empty($reason)) $reason = 'Export/import not supported';

		$info = array(
			'exportable' => $exportable,
			'reason' => $reason,
		);

		$cache[$field->id] = $info;
		
		return $info;
    }
    
    /********************** FROM ProcessWire 3.x: /wire/core/Functions.php ***************************/

	/**
	 * Does given instance (or class) represent an instance of the given className (or class names)?
	 * 
	 * @access private
	 * @param object|string $instance Object instance to test (or string of its class name).
	 * @param string|array $className Class name or array of class names to test against. 
	 * @param bool $autoload
	 * @return bool|string Returns one of the following:
	 *  - boolean false if not an instance (whether $className argument is string or array). 
	 *  - boolean true if given a single $className (string) and $instance is an instance of it. 
	 *  - string of first matching class name if $className was an array of classes to test.
	 * 
	 */
	private function wireInstanceOf($instance, $className, $autoload = true) {
		
		if(is_array($className)) {
			$returnClass = true; 
			$classNames = $className;
		} else {
			$returnClass = false;
			$classNames = array($className);
		}
		
		$matchClass = null;
		$instanceParents = null;

		foreach($classNames as $className) {
			$className = $this->wireClassName($className, true); // with namespace
			if(is_object($instance) && class_exists($className, $autoload)) {
				if($instance instanceof $className) $matchClass = $className;
			} else {
				if(is_null($instanceParents)) {
					$instanceParents = $this->wireClassParents($instance, $autoload);
					$instanceClass = is_string($instance) ? $instance : $this->wireClassName($instance, true);
					$instanceParents[$instanceClass] = 1;
				}
				if(isset($parents[$className])) $matchClass = $className;
			}
			if($matchClass !== null) break;
		}
		
		return $returnClass ? $matchClass : ($matchClass !== null); 
	}

	/**
	 * Normalize a class name with or without namespace
	 * 
	 * Can also be used in an equivalent way to PHP's get_class() function. 
	 * 
	 * @access private
	 * @param string|object $className
	 * @param bool|int|string $withNamespace Should return value include namespace? (default=false) 
	 * 	or specify integer 1 to return only namespace (i.e. "ProcessWire", no leading or trailing backslashes)
	 * @return string|null Returns string or NULL if namespace-only requested and unable to determine
	 * 
	 */
	private function wireClassName($className, $withNamespace = false) {
		
		if(is_object($className)) $className = get_class($className);
		$pos = strrpos($className, "\\");
		
		if($withNamespace === true) {
			// return class with namespace, substituting ProcessWire namespace if none present
			if($pos === false && __NAMESPACE__) $className = __NAMESPACE__ . "\\$className";
			
		} else if($withNamespace === 1) {
			// return namespace only
			if($pos !== false) {
				// there is a namespace
				$className = substr($className, 0, $pos);
			} else {
				// there is no namespace in given className
				$className = null;
			}
				
		} else {
			// return className without namespace
			if($pos !== false) $className = substr($className, $pos+1);
		}
		
		return $className;
	}

	/**
	 * ProcessWire namespace aware version of PHP's class_parents() function
	 * 
	 * Returns associative array where array keys are full namespaced class name, and 
	 * values are the non-namespaced classname.
	 *
	 * @access private
	 * @param string|object $className
	 * @param bool $autoload
	 * @return array
	 *
	 */
	private function wireClassParents($className, $autoload = true) {
		if(is_object($className)) {
			$parents = class_parents($className, $autoload);
		} else {
			$className = $this->wireClassName($className, true);
			if(!class_exists($className, false)) {
				$_className = $this->wireClassName($className, false);
				if(class_exists("\\$_className")) $className = $_className;
			}
			$parents = class_parents(ltrim($className, "\\"), $autoload);
		}
		$a = array();
		if(is_array($parents)) foreach($parents as $k => $v) {
			$v = $this->wireClassName($k, false);
			$a[$k] = $v; // values have no namespace
		}
		return $a; 
	}

	/********************** FROM ProcessWire 3.x: /wire/core/Fieldtype.php ***************************/


	/**
	 * Get associative array of options and info (name => value) that Fieldtype supports for importValue
	 * 
	 * Current recognized options include the following: 
	 * 
	 * - `importable` (bool): Is the field importable (and exportable)? (default=auto-detect)
	 * 
	 * - `test` (bool): Indicates Fieldtype supports testing import before committing & populates notices to 
	 *    returned Wire object. (default=false)
	 * 
	 * - `returnsPageValue` (bool): True if it returns the value that should set back to Page? False if return 
	 *    value should not be set to Page. When false, it indicates the Fieldtype::importValue() handles the 
	 *    actual commit to DB of import data. (default=true)
	 * 
	 * - `requiresExportValue` (bool): Indicates Fieldtype::importValue() requires an 'exportValue' of the 
	 *    current value from Page in $options. (default=false)
	 * 
	 * - `restoreOnException` (bool): Restore previous value if Exception thrown during import (default=false). 
	 * 
	 * #pw-internal
	 * 
	 * @kongondo: @note: not implemented in ProcessWire < 3.x!
	 * Just here to help with creating compatible JSON for PagesExportImport IMPORT.
	 * 
	 * @access public
	 * @param array Field $field
	 * @return array
	 * 
	 */
	public function getImportValueOptions(Field $field) {		
		$schema = $this->getDatabaseSchema($field);		
		$options = array(
			'importable' => (!isset($schema['xtra']['all']) || $schema['xtra']['all'] !== true) ? false : true,
			'test' => false,
			'returnsPageValue' => true,
			'requiresExportValue' => false,
			'restoreOnException' => false,
		);
		return $options; 
	}	

	/**
	 * Get the database schema for this field
	 *
	 * - Should return an array like in the example below, indexed by field name with type details as the value 
	 *   (as it would be in an SQL statement).
	 * 
	 * - Indexes are passed through with a `keys` array. Note that `pages_id` as a field and primary key may be 
	 *   retrieved by starting with the parent schema return from the built-in getDatabaseSchema() method.
	 * 
	 * - At minimum, each Fieldtype must add a `data` field as well as an index for it.
	 *
	 * - If you want a PHP `NULL` value to become a NULL in the database, your column definition must specify: 
	 *   `DEFAULT NULL`.
	 *
	 * ~~~~~~
	 * array(
	 *  'data' => 'mediumtext NOT NULL', 
	 *  'keys' => array(
	 *    'primary' => 'PRIMARY KEY (`pages_id`)', 
	 *    'FULLTEXT KEY data (data)', 
	 *  ),
	 *  'xtra' => array(
	 *    // optional extras, MySQL defaults will be used if omitted
	 *    'append' => 
	 *      'ENGINE={$this->config->dbEngine} ' . 
	 *      'DEFAULT CHARSET={$this->config->dbCharset}'
	 * 
	 *    // true (default) if this schema provides all storage for this fieldtype.
	 *    // false if other storage is involved with this fieldtype, beyond this schema 
	 *    // (like repeaters, PageTable, etc.)
	 *    'all' => true, 
	 *  )
	 * );
	 * ~~~~~~
	 * 
	 * #pw-group-creating
	 *
	 * @access public
	 * @param Field $field In case it's needed for the schema, but typically isn't. 
	 * @return array
	 *
	 */
	public function getDatabaseSchema(Field $field) {
		if($field) {}
		$engine = $this->wire('config')->dbEngine; 
		$charset = $this->wire('config')->dbCharset;
		$schema = array(
			'pages_id' => 'int UNSIGNED NOT NULL', 
			'data' => "int NOT NULL", // each Fieldtype should override this in particular
			'keys' => array(
				'primary' => 'PRIMARY KEY (`pages_id`)', 
				'data' => 'KEY data (`data`)',
			),
			// additional data 
			'xtra' => array(
				// any optional statements that should follow after the closing paren (i.e. engine, default charset, etc)
				'append' => "ENGINE=$engine DEFAULT CHARSET=$charset", 
				
				// true (default) if this schema provides all storage for this fieldtype.
				// false if other storage is involved with this fieldtype, beyond this schema (like repeaters, PageTable, etc.)
				'all' => true, 
			)
		); 
		return $schema; 
	}

	/********************** FROM ProcessWire 3.x: /wire/core/WireFileTools.php ***************************/

	/**
	 * Create a directory that is writable to ProcessWire and uses the defined $config chmod settings
	 * 
	 * Unlike PHP's `mkdir()` function, this function manages the read/write mode consistent with ProcessWire's
	 * setting `$config->chmodDir`, and it can create directories recursively. Meaning, if you want to create directory /a/b/c/ 
	 * and directory /a/ doesn't yet exist, this method will take care of creating /a/, /a/b/, and /a/b/c/. 
	 * 
	 * The `$recursive` and `$chmod` arguments may optionally be swapped (since 3.0.34).
	 * 
	 * ~~~~~
	 * // Create a new directory in ProcessWire's cache dir
	 * if($files->mkdir($config->paths->cache . 'foo-bar/')) {
	 *   // directory created: /site/assets/cache/foo-bar/
	 * }
	 * ~~~~~
	 *
	 * @access public
	 * @param string $path Directory you want to create
	 * @param bool|string $recursive If set to true, all directories will be created as needed to reach the end.
	 * @param string|null|bool $chmod Optional mode to set directory to (default: $config->chmodDir), format must be a string i.e. "0755"
	 *   If omitted, then ProcessWire's `$config->chmodDir` setting is used instead.
	 * @return bool True on success, false on failure
	 *
	 */
	public function mkdir($path, $recursive = false, $chmod = null) {
		if(!strlen($path)) return false;
		
		if(is_string($recursive) && strlen($recursive) > 2) {
			// chmod argument specified as $recursive argument or arguments swapped
			$_chmod = $recursive;
			$recursive = is_bool($chmod) ? $chmod : false;
			$chmod = $_chmod;
		}
		
		if(!is_dir($path)) {
			if($recursive) {
				$parentPath = substr($path, 0, strrpos(rtrim($path, '/'), '/'));
				if(!is_dir($parentPath) && !$this->mkdir($parentPath, true, $chmod)) return false;
			}
			if(!@mkdir($path)) return false;
		}
		$this->chmod($path, false, $chmod);
		return true;
	}

	/**
	 * Change the read/write mode of a file or directory, consistent with ProcessWire's configuration settings
	 * 
	 * Unless a specific mode is provided via the `$chmod` argument, this method uses the `$config->chmodDir`
	 * and `$config->chmodFile` settings in /site/config.php. 
	 * 
	 * This method also provides the option of going recursive, adjusting the read/write mode for an entire
	 * file/directory tree at once. 
	 * 
	 * The `$recursive` or `$chmod` arguments may be optionally swapped in order (since 3.0.34).
	 * 
	 * ~~~~~
	 * // Update the mode of /site/assets/cache/foo-bar/ recursively
	 * $files->chmod($config->paths->cache . 'foo-bar/', true); 
	 * ~~~~~
	 *
	 * @access public
	 * @param string $path Path or file that you want to adjust mode for (may be a path/directory or a filename).
	 * @param bool|string $recursive If set to true, all files and directories in $path will be recursively set as well (default=false). 
	 * @param string|null|bool $chmod If you want to set the mode to something other than ProcessWire's chmodFile/chmodDir settings,
	 * you may override it by specifying it here. Ignored otherwise. Format should be a string, like "0755".
	 * @return bool Returns true if all changes were successful, or false if at least one chmod failed.
	 * @throws WireException when it receives incorrect chmod format
	 *
	 */
	public function chmod($path, $recursive = false, $chmod = null) {
		
		if(is_string($recursive) && strlen($recursive) > 2) {
			// chmod argument specified as $recursive argument or arguments swapped
			$_chmod = $recursive;
			$recursive = is_bool($chmod) ? $chmod : false;
			$chmod = $_chmod; 
		}

		if(is_null($chmod)) {
			// default: pull values from PW config
			$chmodFile = $this->wire('config')->chmodFile;
			$chmodDir = $this->wire('config')->chmodDir;
		} else {
			// optional, manually specified string
			if(!is_string($chmod)) throw new WireException("chmod must be specified as a string like '0755'");
			$chmodFile = $chmod;
			$chmodDir = $chmod;
		}

		$numFails = 0;

		if(is_dir($path)) {
			// $path is a directory
			if($chmodDir) if(!@chmod($path, octdec($chmodDir))) $numFails++;

			// change mode of files in directory, if recursive
			if($recursive) foreach(new \DirectoryIterator($path) as $file) {
				if($file->isDot()) continue;
				$mod = $file->isDir() ? $chmodDir : $chmodFile;
				if($mod) if(!@chmod($file->getPathname(), octdec($mod))) $numFails++;
				if($file->isDir()) {
					if(!$this->chmod($file->getPathname(), true, $chmod)) $numFails++;
				}
			}
		} else {
			// $path is a file
			$mod = $chmodFile;
			if($mod) if(!@chmod($path, octdec($mod))) $numFails++;
		}

		return $numFails == 0;
	}

	/**
	 * Creates a ZIP file
	 * 
	 * ~~~~~
	 * // Create zip of all files in directory $dir to file $zip
	 * $dir = $config->paths->cache . "my-files/"; 
	 * $zip = $config->paths->cache . "my-file.zip";
	 * $result = $files->zip($zip, $dir); 
	 *  
	 * echo "<h3>These files were added to the ZIP:</h3>";
	 * foreach($result['files'] as $file) {
	 *   echo "<li>" $sanitizer->entities($file) . "</li>";
	 * }
	 * 
	 * if(count($result['errors'])) {
	 *   echo "<h3>There were errors:</h3>";
	 *   foreach($result['errors'] as $error) {
	 *     echo "<li>" . $sanitizer->entities($error) . "</li>";
	 *   }
	 * }
	 * ~~~~~
	 *
	 * @access public
	 * @param string $zipfile Full path and filename to create or update (i.e. /path/to/myfile.zip)
	 * @param array|string $files Array of files to add (full path and filename), or directory (string) to add.
	 *   If given a directory, it will recursively add everything in that directory.
	 * @param array $options Associative array of options to modify default behavior:
	 *  - `allowHidden` (boolean or array): allow hidden files? May be boolean, or array of hidden files (basenames) you allow. (default=false)
	 *    Note that if you actually specify a hidden file in your $files argument, then that overrides this.
	 *  - `allowEmptyDirs` (boolean): allow empty directories in the ZIP file? (default=true)
	 *  - `overwrite` (boolean): Replaces ZIP file if already present (rather than adding to it) (default=false)
	 *  - `maxDepth` (int): Max dir depth 0 for no limit (default=0). Specify 1 to stay only in dirs listed in $files. 
	 *  - `exclude` (array): Files or directories to exclude
	 *  - `dir` (string): Directory name to prepend to added files in the ZIP
	 * @return array Returns associative array of:
	 *  - `files` (array): all files that were added
	 *  - `errors` (array): files that failed to add, if any
	 * @throws WireException Original ZIP file creation error conditions result in WireException being thrown.
	 * @see WireFileTools::unzip()
	 *
	 */
	public function zip($zipfile, $files, array $options = array()) {
		
		static $depth = 0;

		$defaults = array(
			'allowHidden' => false,
			'allowEmptyDirs' => true,
			'overwrite' => false,
			'maxDepth' => 0, 
			'exclude' => array(), // files or dirs to exclude
			'dir' => '',
			'zip' => null, // internal use: holds ZipArchive instance for recursive use
		);

		$return = array(
			'files' => array(),
			'errors' => array(),
		);
		
		if(!empty($options['zip']) && !empty($options['dir']) && $options['zip'] instanceof \ZipArchive) {
			// internal recursive call
			$recursive = true;
			$zip = $options['zip']; // ZipArchive instance

		} else if(is_string($zipfile)) {
			if(!class_exists('\ZipArchive')) throw new WireException("PHP's ZipArchive class does not exist");
			$options = array_merge($defaults, $options);
			$zippath = dirname($zipfile);
			if(!is_dir($zippath)) throw new WireException("Path for ZIP file ($zippath) does not exist");
			if(!is_writable($zippath)) throw new WireException("Path for ZIP file ($zippath) is not writable");
			if(empty($files)) throw new WireException("Nothing to add to ZIP file $zipfile");
			if(is_file($zipfile) && $options['overwrite'] && !unlink($zipfile)) throw new WireException("Unable to overwrite $zipfile");
			if(!is_array($files)) $files = array($files);
			if(!is_array($options['exclude'])) $options['exclude'] = array($options['exclude']);
			$recursive = false;
			$zip = new \ZipArchive();
			if($zip->open($zipfile, \ZipArchive::CREATE) !== true) throw new WireException("Unable to create ZIP: $zipfile");

		} else {
			throw new WireException("Invalid zipfile argument");
		}

		$dir = strlen($options['dir']) ? rtrim($options['dir'], '/') . '/' : '';

		foreach($files as $file) {
			$basename = basename($file);
			$name = $dir . $basename;
			if($basename[0] == '.' && $recursive) {
				if(!$options['allowHidden']) continue;
				if(is_array($options['allowHidden']) && !in_array($basename, $options['allowHidden'])) continue;
			}
			if(count($options['exclude'])) {
				if(in_array($name, $options['exclude']) || in_array("$name/", $options['exclude'])) continue;
			}
			if(is_dir($file)) {
				if($options['maxDepth'] > 0 && $depth >= $options['maxDepth']) continue;
				$_files = array();
				foreach(new \DirectoryIterator($file) as $f) {
					if($f->isDot()) continue; 
					if($options['maxDepth'] > 0 && $f->isDir() && ($depth+1) >= $options['maxDepth']) continue;
					$_files[] = $f->getPathname();
				}
				if(count($_files)) {
					$zip->addEmptyDir($name);
					$options['dir'] = "$name/";
					$options['zip'] = $zip;
					$depth++;
					$_return = $this->zip($zipfile, $_files, $options);
					$depth--;
					foreach($_return['files'] as $s) $return['files'][] = $s;
					foreach($_return['errors'] as $s) $return['errors'][] = $s;
				} else if($options['allowEmptyDirs']) {
					$zip->addEmptyDir($name);
				}
			} else if(file_exists($file)) {
				if($zip->addFile($file, $name)) {
					$return['files'][] = $name;
				} else {
					$return['errors'][] = $name;
				}
			}
		}

		if(!$recursive) $zip->close();

		return $return;
	}

	/********************** FROM ProcessWire 3.x: /wire/core/WireHttp.php ***************************/

	/**
	 * Send the contents of the given filename to the current http connection.
	 *
	 * This function utilizes the `$config->fileContentTypes` to match file extension
	 * to content type headers and force-download state.
	 *
	 * This function throws a `WireException` if the file can't be sent for some reason.
	 *
	 * @access public
	 * @param string $filename Filename to send
	 * @param array $options Options that you may pass in:
	 *   - `exit` (bool): Halt program executation after file send (default=true). 
	 *   - `forceDownload` (bool|null): Whether file should force download (default=null, i.e. let content-type header decide).
	 *   - `downloadFilename` (string): Filename you want the download to show on user's computer, or omit to use existing.
	 * @param array $headers Headers that are sent. These are the defaults: 
	 *   - `pragma`: public
	 *   - `expires`: 0
	 *   - `cache-control`: must-revalidate, post-check=0, pre-check=0
	 *   - `content-type`: {content-type} (replaced with actual content type)
	 *   - `content-transfer-encoding`: binary
	 *   - `content-length`: {filesize} (replaced with actual filesize)
	 *   - To remove a header completely, make its value NULL and it won't be sent.
	 * @throws WireException
	 *
	 */
	public function sendFile($filename, array $options = array(), array $headers = array()) {
		// @kongondo: @note: this method not in ProcessWire 2.7 WireHttp class, hence copied here

		$_options = array(
			// boolean: halt program execution after file send
			'exit' => true,
			// boolean|null: whether file should force download (null=let content-type header decide)
			'forceDownload' => null,
			// string: filename you want the download to show on the user's computer, or blank to use existing.
			'downloadFilename' => '',
		);

		$_headers = array(
			"pragma" => "public",
			"expires" =>  "0",
			"cache-control" => "must-revalidate, post-check=0, pre-check=0",
			"content-type" => "{content-type}",
			"content-transfer-encoding" => "binary",
			"content-length" => "{filesize}",
		);
		// @kongondo: @note: PW version < 2.4 don't have the method
		//$this->wire('session')->close();// @kongondo
		if(method_exists($this->wire('session'), 'close')) $this->wire('session')->close();
		$options = array_merge($_options, $options);
		$headers = array_merge($_headers, $headers);
		if(!is_file($filename)) throw new WireException("File does not exist");
		$info = pathinfo($filename);
		$ext = strtolower($info['extension']);
		$contentTypes = $this->wire('config')->fileContentTypes;
		$contentType = isset($contentTypes[$ext]) ? $contentTypes[$ext] : $contentTypes['?'];
		$forceDownload = $options['forceDownload'];
		if(is_null($forceDownload)) $forceDownload = substr($contentType, 0, 1) === '+';
		$contentType = ltrim($contentType, '+');
		if(ini_get('zlib.output_compression')) ini_set('zlib.output_compression', 'Off');
		$tags = array('{content-type}' => $contentType, '{filesize}' => filesize($filename));

		foreach($headers as $key => $value) {
			if(is_null($value)) continue;
			if(strpos($value, '{') !== false) $value = str_replace(array_keys($tags), array_values($tags), $value);
			header("$key: $value");
		}

		if($forceDownload) {
			$downloadFilename = empty($options['downloadFilename']) ? $info['basename'] : $options['downloadFilename'];
			header("content-disposition: attachment; filename=\"$downloadFilename\"");
		}

		@ob_end_clean();
		@flush();
		readfile($filename);
		if($options['exit']) exit;
	}


	/********************** FROM ProcessWire 3.x: /wire/modules/Fieldtype/FieldtypeFile.module ***************************/

	/**
	 * Export value of a FieldtypeFile.
	 * 
	 * @note: code borrowed from ProcessWire 3.x FieldtypeFile::___exportValue()
	 *
	 * @access public
	 * @param Page $page
	 * @param Field $field
	 * @param [type] $value
	 * @param array $options
	 * @return void
	 * 
	 */
	//public function ___exportValue(Page $page, Field $field, $value, array $options = array()) {
	public function exportValueFile(Page $page, Field $field, $value, array $options = array()) {
		
		$pagefiles = $value;
		
		#$value = $this->sleepValue($page, $field, $value); 
		$value = $field->type->sleepValue($page, $field, $value);// @kongondo
		$exportValue = array();
		$defaults = array(
			'noJSON' => false, // no JSON for exported file descriptions or other properties (use array instead)
		);
		if(!isset($options['FieldtypeFile'])) $options['FieldtypeFile'] = array();
		$options['FieldtypeFile'] = array_merge($defaults, $options['FieldtypeFile']); 
		
		foreach($value as $k => $v) {
			/** @var Pagefile $pagefile */
			$pagefile = $pagefiles->get($v['data']); 

			// @kongondo 	
			
			$a = array(
				//'url' => $pagefile->httpUrl(),// @kongondo
				// @kongondo @note: PW version < 2.4 does not support Pagefile::httpUrl())
				// ...so we check and build httpUrl if necessary
				'url' => $this->getHttpUrl($page, $pagefile),
				'size' => $pagefile->filesize(), 
			); 
			
			if(!empty($options['system'])) {
				unset($v['created'], $v['modified']);
				$exportKey = $v['data'];
			} else {
				$a['name'] = $v['data'];
				$exportKey = $k;
			}
			
			unset($v['data']); 
	
			if($options['FieldtypeFile']['noJSON']) {
				// export version 2 for exported description uses array value for multi-language, rather than JSON string
				if(!isset($v['description'])) $v['description'] = '';
				$v['description'] = $this->exportDescriptionFile($v['description']);
			}
			
			$exportValue[$exportKey] = array_merge($a, $v);
		}
		
		return $exportValue; 	
	}

	/**
	 * Export description value to array (multi-language, indexed by lang name) or string (non-multi-language)
	 * 
	 * @note: code borrowed from FieldtypeFile::exportDescription()
	 * 
	 * @access protected
	 * @param string|array $value
	 * @return array|string
	 * 
	 */
	//protected function exportDescription($value) {
	protected function exportDescriptionFile($value) {
		
		/** @var Languages $languages */
		$languages = $this->wire('languages');
		
		if(is_string($value)) {
			if(strpos($value, '[') !== 0 && strpos($value, '{') !== 0) return $value;
			$a = json_decode($value, true);
			if(!is_array($a)) return $value;
		} else if(is_array($value)) {
			$a = $value;
		} else {
			$a = array();
		}
	
		if(!$languages) {
			$value = count($a) ? (string) reset($a) : ''; // return first/default
			return $value; 
		}

		$description = array(); 
		
		// ensure value present for every language, even if blank
		foreach($languages as $language) {
			$description[$language->name] = '';
		}
		
		foreach($a as $langKey => $langVal) {
			if(ctype_digit("$langKey")) $langKey = (int) $langKey;
			if(empty($langKey)) {
				$langKey = 'default';
			} else {
				$langKey = $languages->get($langKey)->name;
				if(empty($langKey)) continue;
			}
			$description[$langKey] = $langVal;
		}
		
		return $description;
	}


	/********************** FROM ProcessWire 3.x: /wire/modules/Fieldtype/FieldtypeImage.module ***************************/

	/**
	 * Export value of Pageimages value to a portable PHP array.
	 * 
	 * @note: code borrowed from FieldtypeImage::___exportValue().
	 * 
	 * @access public
	 * @param Page $page
	 * @param Field $field
	 * @param array|float|int|null|object|string $value
	 * @param array $options
	 * @return array
	 * 
	 */
	//public function ___exportValue(Page $page, Field $field, $value, array $options = array()) {
	public function exportValueImage(Page $page, Field $field, $value, array $options = array()) {
		/** @var Pageimages $pagefiles */
		$pagefiles = $value; 
		//$value = parent::___exportValue($page, $field, $value, $options); 
		$value = $this->exportValueFile($page, $field, $value, $options); 
		if(empty($options['system'])) {
			foreach($value as $k => $v) {
				$img = $pagefiles->get($v['name']);
				$value[$k]['width'] = $img->width();
				$value[$k]['height'] = $img->height();
			}
		}
		
		if(!empty($options['FieldtypeImage'])) {
			$o = $options['FieldtypeImage']; 
			if(!empty($o['variations'])) {
				// include URLs to image variations
				foreach($value as $k => $v) {
					if(empty($options['system'])) {
						$img = $pagefiles->get($v['name']);
					} else {
						$img = $pagefiles->get($k); 
					}
					$variations = array();
					foreach($img->getVariations() as $variation) {
						/** @var Pageimage $variation */
						// @kongondo @note: PW version < 2.4 does not support Pagefile::httpUrl())
						// ...so we check and build httpUrl if necessary
						//$variations[$variation->name] = $variation->httpUrl();
						$variations[$variation->name] = $this->getHttpUrl($page, $variation);
					}
					$value[$k]['variations'] = $variations;
					
				}
			}
		}		
		
		return $value; 	
	}

	/********************** FROM ProcessWire 3.x: /wire/modules/Fieldtype/FieldtypePage.module ***************************/

	// @kongondo: modified

	/**
	 * Export Page value to a portable PHP array.
	 * 
	 * @note: code borrowed from FieldtypePage::___exportValue()
	 * 
	 * @access public
	 * @param Page $page
	 * @param Field $field
	 * @param array|int|object|string $value
	 * @param array $options
	 * @return array|string
	 * 
	 */
	//public function ___exportValue(Page $page, Field $field, $value, array $options = array()) {
	public function exportValuePages(Page $page, Field $field, $value, array $options = array()) {
		if($value instanceof Page) return $this->exportValuePage($page, $field, $value, $options); 	
		if(!$value instanceof PageArray) return array();
		$a = array();
		foreach($value as $k => $v) {
			$a[] = $this->exportValuePage($page, $field, $v, $options); 	
		}
		// in human mode just return the titles separated by a carriage return
		if(!empty($options['human'])) return implode("\n", $a); 
		return $a; 
	}
	
	/**
	 * Export Page value to a portable PHP array.
	 * 
	 * @note: code borrowed from FieldtypePage::exportValuePage()
	 *
	 * @access protected
	 * @param Page $page
	 * @param Field $field
	 * @param Page $value
	 * @param array $options
	 * @return void
	 */
	protected function exportValuePage(Page $page, Field $field, Page $value, array $options = array()) {
		if($page) {}
		if($field) {}
		if(!$value->id) return array();
		// in human mode, just return the title or name
		if(!empty($options['human'])) {
			return (string) $value->get('title|name');
		}
		// otherwise return an array of info
		$a = array(); 
		if(!empty($options['system'])) {
			$a = $value->path;	
		} else {
			if($value->template && $value->template->fieldgroup->has('title')) {
				$a['title'] = (string) $value->getUnformatted('title');
			}
			$a['id'] = $value->id;
			$a['name'] = $value->name;
			$a['path'] = $value->path;
			$a['template'] = (string) $value->template;
			$a['parent_id'] = $value->parent_id;
		}
		return $a; 
	}

	/********************** FROM ProcessWire 3.x: /wire/modules/Fieldtype/FieldtypeRepeater.module ***************************/

	/**
	 * Export repeater value
	 * 
	 * @note: code borrowed from FieldtypeRepeater::___exportValue()
	 * 
	 * @access public
	 * @param Page $page
	 * @param Field $field
	 * @param RepeaterPageArray$value
	 * @param array $options
	 *  - `minimal` (bool): Export a minimal array of just fields and values indexed by repeater page name (default=false)
	 * @return array
	 * 
	 */
	//public function ___exportValue(Page $page, Field $field, $value, array $options = array()) {
	public function exportValueRepeater(Page $page, Field $field, $value, array $options = array()) {
		
		$a = array();
		if(!WireArray::iterable($value)) return $a;
	
		if(!empty($options['minimal']) || !empty($options['FieldtypeRepeater']['minimal'])) {
			// minimal export option includes only fields data
			
			foreach($value as $k => $p) {
				/** @var Page $p */
				if($p->isUnpublished()) continue;
				$v = array(); 
				foreach($p->template->fieldgroup as $f) {
					if(!$p->hasField($f)) continue;
					$v[$f->name] = $f->type->exportValue($p, $f, $p->getUnformatted($f->name), $options);
				}
				$a[$p->name] = $v;
			}
			
		} else {
			// regular export
			/** @var PagesExportImport $exporter */
			//$exporter = $this->wire(new PagesExportImport());// @kongondo
			//$a = $exporter->pagesToArray($value, $options);// @kongondo
			$a = $this->pagesToArray($value, $options);// @kongondo
		}
		
		return $a;
	}

	/********************** FROM ProcessWire 3.x: /wire/core/Fieldtype/WireTempDir.php ***************************/

	/**
	 * Create the temporary directory
	 * 
	 * This method should only be called once per instance of this class. If you specified a $name argument
	 * in the constructor, then you should not call this method because it will have already been called. 
	 * 
	 * @access public
	 * @param string|object $name Recommend providing the object that is using the temp dir, but can also be any string
	 * @param string $basePath Base path where temp dirs should be created. Omit to use default (recommended).
	 * @throws WireException if given a $root that doesn't exist
	 * @return string Returns the root of the temporary directory. Use the get() method to get a dir for use. 
	 *
	 */
	public function create($name = '', $basePath = '') {

		if(!is_null($this->tempDirRoot)) throw new WireException("Temp dir has already been created");
		if(empty($name)) $name = $this->createName();
		#if(is_object($name)) $name = wireClassName($name, false);// @kongondo
		if(is_object($name)) $name = $this->wireClassName($name, false);

		if($basePath) {
			// they provide base path
			$basePath = rtrim($basePath, '/') . '/'; // ensure it ends with trailing slash
			if(!is_dir($basePath)) throw new WireException("Provided base path doesn't exist: $basePath");
			if(!is_writable($basePath)) throw new WireException("Provided base path is not writiable: $basePath");

		} else {
			// we provide base path (root)
			$basePath = $this->wire('config')->paths->cache;
			if(!is_dir($basePath)) $this->mkdir($basePath);
		}

		#$basePath .= wireClassName($this, false) . '/';// @kongondo
		$basePath .= $this->wireClassName($this, false) . '/';
		$this->classRoot = $basePath;
		if(!is_dir($basePath)) $this->mkdir($basePath);

		$this->tempDirRoot = $basePath . ".$name/";
		
		return $this->tempDirRoot;
	}

	/**
	 * Create a randomized name for runtime temp dir
	 * 
	 * @access public
	 * @param string $prefix Optional prefix for name
	 * @return string
	 * 
	 */
	public function createName($prefix = '') {
		$pass = new Password();
		$this->wire($pass);// @kongondo: @todo?: we are not using this method (createName) so no need for this?
		$len = mt_rand(10, 30);
		$name = microtime() . '.' . $pass->randomBase64String($len, true);
		$a = explode('.', $name);
		shuffle($a);
		$name = $prefix . implode('O', $a);
		$this->createdName = $name;
		return $name;
	}

	/**
	 * Returns a temporary directory (path) 
	 *
	 * @access public
	 * @param string $id Optional identifier to use (default=autogenerate)
	 * @return string Returns path
	 * @throws WireException If can't create temporary dir
	 *
	 */
	//public function get($id = '') {
	public function getTempDir($id = '') {// @kongondo
		
		static $level = 0;
		
		if(is_null($this->tempDirRoot)) throw new WireException("Please call the create() method before the get() method"); 

		// first check if cached result from previous call
		if(!is_null($this->tempDir) && file_exists($this->tempDir)) return $this->tempDir;

		// find unique temp dir
		$level++;
		$n = 0;
		do {
			if($id) {
				$tempDir = $this->tempDirRoot . $id . ($n ? "$n/" : "/");
				if(!$n) $id .= "-"; // i.e. id-1, for next iterations
			} else {
				$tempDir = $this->tempDirRoot . "$n/";
			}
			if(!is_dir($tempDir)) break;
			$n++;
			/*
			if($exists) {
				// check if we can remove existing temp dir
				$time = filemtime($tempDir);
				if($time < time() - $this->tempDirMaxAge) { // dir is old and can be removed
					if($this->rmdir($tempDir, true)) $exists = false;
				}
			}
			*/
		} while(1);

		// create temp dir
		if(!$this->mkdir($tempDir, true)) {
			clearstatcache();
			if(!is_dir($tempDir) && !$this->mkdir($tempDir, true)) {
				if($level < 5) {
					// try again, recursively
					clearstatcache();
					#$tempDir = $this->get($id . "L$level");// @kongondo
					$tempDir = $this->getTempDir($id . "L$level");
				} else {
					$level--;
					throw new WireException("Unable to create temp dir: $tempDir");
				}
			}
		}

		// cache result
		$this->tempDir = $tempDir;
		$level--;
		
		return $tempDir;
	}

}
