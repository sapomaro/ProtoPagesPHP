<?php

/*
# ProtoPages v0.5 
## PHP Static Site Generator & Template Builder

Create 'template.pp' text file (or several files with '*.pp' extension) in your 'import_folder'

Use the following syntax in '*.pp' files for templating pages:
	%REF%	pageName, pageAlias, pageClass
	%URL%	path/to/page/
	%CODE%	contents of the page (if you use %CUSTOM_VAR% here, it will be replaced with 'custom value')
	%USE%	template.html || %REF% of another page // <<< uses the code and props of another page
	%CUSTOM_VAR%	custom value

Separate entries of different pages with empty lines. 

See 'template.pp' & 'template.html' for more functionality.

To build your site use:

	<?php 
	
	require_once('protopages.php');

	$website = new ProtoSite('import_folder/', 'build_folder/', 'mywebsite.com'); // domain name is used for url resolving

	$website->pages['main']->show();

	$website->dataExport();
	
	?>

*/


mb_internal_encoding("UTF-8"); 
mb_regex_encoding("UTF-8");

class ProtoSite {
	static function aliasPattern() { 
		return '%([A-Za-z0-9_\,]+)\[?([^%\]]+)?\]?%'; 
	}
	static function refPattern() { 
		return '@([A-Za-z0-9_\,]+)'; 
	}
	
	function __construct($import_path, $export_path, $domain = null) {
		$this->pages = [];
		$this->pageFiles = [];
		$this->subFiles = [];
		$this->importDir = $import_path;
		$this->exportDir = $export_path;
		$this->localExportDir = true;
		$this->domainBase = $domain;
		$this->scriptDir = getcwd();
		
		
		$this->dataImport();
		$this->dataBuild();
		
		$this->listFiles();
	}
	
	function dataBuild(&$instance = null) {
		foreach ($this->pages as &$page) {
			if ($instance && $instance !== $page) { continue; }
			$page->dataExtend();
		}
		foreach ($this->pages as &$page) {
			if ($instance && $instance !== $page) { continue; }
			$page->dataExtend();
			$page->templatePreserve();
			$page->aliasResolve();
		}
		foreach ($this->pages as &$page) {
			if ($instance && $instance !== $page) { continue; }
			$page->aliasResolve();
		}
		foreach ($this->pages as &$page) {
			if ($instance && $instance !== $page) { continue; }
			$page->templateResolve();
			$page->aliasResolve();
			$page->codeNormalize();
			$page->codeAppend();
		}	
		foreach ($this->pages as &$page) {
			if ($instance && $instance !== $page) { continue; }
			$page->linksResolve();
		}
	}
	
	
	function dataImport() {
		$path = $this->importDir;
		$dir_handle = opendir($path);
		if (!$dir_handle) { exit('dir handle error'); }
		while(($entry = readdir($dir_handle)) !== false) {
			if ($entry === '.' || $entry === '..') { continue; }
			$ext = explode('.', $entry);
			$ext = end($ext);
			if (mb_strtolower($ext) !== 'pp') { continue; }
			$file_handle = fopen($path.$entry, 'r');
			if (!$file_handle) { exit('file handle error'); }
			$proto = null;
			$prop = null;
			$delim = null;
			$val = null;
			while(($line = fgets($file_handle)) !== false) {
				$line = rtrim($line);
				if ($line === '') { continue; }
				if (mb_ereg('^'. self::aliasPattern() .'[ \t]*(.+)$', $line, $parsed)) { 
					if ($prop !== null && $delim !== null && $val !== null) {
						if ($prop === 'ref') {
							$this->dataInstance($proto);
							$proto = [];
						}
						$proto[$prop] = mb_split($delim, $val);
					}	
					$prop = mb_strtolower($parsed[1]);
					
					if ($prop === 'ref' || $prop === 'use') { 
						$delim = '[, ]+'; 
					}
					elseif ($parsed[2] !== false) { 
						//if ($parsed[2] === '|') { $parsed[2] = '\|'; }
						$delim = '\s*'.preg_quote($parsed[2]).'\s*'.'|\n';
					}
					else{ 
						$delim = '\n';
					}
					if ($parsed[3] !== false) { $val = $parsed[3]; }
					else{ $val = ''; }
				}
				else{
					$val .= "\n".ltrim($line);
				}
			}
			if ($prop !== null && $delim !== null && $val !== null) {
				$proto[$prop] = mb_split($delim, $val);
			}
			$this->dataInstance($proto);
			fclose($file_handle);
		}
		closedir($dir_handle);
		
	}
	function addPage($proto) {
		$page = $this->dataInstance($proto);
		if ($page) {
			$this->dataBuild($page);
			$this->listFiles();
			return $page;
		}
	}
	function dataInstance($proto) {
		if ($proto !== null) {
			$page = new ProtoPage($proto);
			$page->parent = &$this;
			$page->date = [date("Y-m-d")];
			if (isset($page->url)) {
				$fname = mb_split('\/', $page->url[0]);
				if (array_pop($fname) === '') { $page->file = [$page->url[0] . 'index.html']; }
				else{ $page->file = [$page->url[0]]; }
			}
			$page->subFiles = [];
			$this->pages[$page->ref[0]] = &$page;
			return $page;
		}
	}

	
	function clearFiles($dir) {
		/*if (mb_strpos(realpath($this->exportDir), $this->scriptDir) === false) {
			die('SCRIPT PATH !> EXPORT PATH');
		}*/
		if (!$dir) { return; }
		if (substr($dir, -1) != "/") { $dir = $dir."/"; }
		if (!is_dir($dir)) { return; }
		if ($dir_handler = opendir($dir)) {
			while($obj = readdir($dir_handler)) {
				if ($obj == "." || $obj == "..") { continue; }
				if (is_dir($dir.$obj)) { 
					$this->clearFiles($dir.$obj); 
				}
				elseif (is_file($dir.$obj)) {
					$fname = mb_ereg_replace('^'.preg_quote($this->exportDir), '', $dir.$obj);
					if (!in_array($fname, $this->pageFiles) && !in_array($fname, $this->subFiles)) {
						
						if (mb_strpos(realpath($dir.$obj), realpath($this->exportDir)) === false) {
							die('FILE TO DELETE is out of scope of EXPORT PATH');
						}
						if ($this->localExportDir && mb_strpos(realpath($dir.$obj), $this->scriptDir) === false) {
							die('FILE TO DELETE must be in the SCRIPT DIR');
						}
						unlink($dir.$obj);
					}
				}
			}
		}
		if (count(scandir($dir)) == 2) { 
			if (mb_strpos(realpath($dir), realpath($this->exportDir)) === false) {
				// mb_strpos(realpath($dir), $this->scriptDir) === false
				die('DIR TO DELETE is out of scope of EXPORT PATH');
			}
			if ($this->localExportDir && mb_strpos(realpath($dir), $this->scriptDir) === false) {
				die('DIR TO DELETE must be in the SCRIPT DIR');
			}
			rmdir($dir); 
		}
	}
	
	
	function writePage(&$page) {
		if (!isset($page->file)) { return null; }
		$dir = $this->exportDir;
		$fname = $page->file[0];
		$dirname = dirname($dir.$fname);		    
		if (!is_dir($dirname)) { mkdir($dirname, 0, true); }
		if (!isset($page->updated) && isset($page->code)) {
			file_put_contents($dir.$fname, $page->code); 
echo "FILE WRITTEN: $dir$fname<br>";
		}
	}

	function listFiles() {
		$dir = $this->exportDir;
		foreach ($this->pages as &$page) {
			if (isset($page->file) && !in_array($page->file[0], $this->pageFiles)) {
				$fname = $page->file[0];
				if ($fname[0] === '/') { $fname = mb_substr($fname, 1); }
				$this->pageFiles[] = $fname;
				if (is_file($dir.$fname)) {
					$file_code = file_get_contents($dir.$fname);
					if ($file_code === $page->code) {
						$page->date = [date("Y-m-d", filemtime($dir.$fname))];
						$page->updated = true;
					}
				}
				$this->subFiles = array_merge($this->subFiles, $page->subFiles);
			}
		}
		$this->subFiles = array_unique($this->subFiles); 
		foreach ($this->subFiles as $id => &$fname) {
			if (!$fname) { unset($this->subFiles[$id]); continue; }
			if ($fname[0] === '/') { $fname = mb_substr($fname, 1); }
		}
	
	}
	
	
	function dataExport() {	
		
		$this->clearFiles($this->exportDir);
		
		foreach ($this->pages as &$page) {
			$this->writePage($page);
		}

		foreach ($this->subFiles as $fname) {
			if (mb_strtolower(pathinfo($fname, PATHINFO_EXTENSION)) !== 'css') { continue; }
			$code = file_get_contents($this->importDir.$fname);
			if (!$code) { continue; }
			if (mb_ereg_search_init($code, '(url\s?\([\"\'\s]*)([^\"\'\s\)]+)')) { 
				while(list($expr,,$url) = mb_ereg_search_regs()) {
					if ($url[0] === '/' || mb_ereg('^https?\:\/\/', $url)) { continue; }
					if (!in_array($url, $this->subFiles)) { $this->subFiles[] = $url; }
				}
			}
		}
		foreach ($this->subFiles as $fname) {
			$dirname = dirname($this->exportDir.$fname);		    
			if (!is_dir($dirname)) { mkdir($dirname, 0, true); }
			if (!file_exists($this->importDir.$fname) || is_dir($this->importDir.$fname)) { continue; }
			if (!$this->files_are_equal($this->importDir.$fname, $this->exportDir.$fname)) {
				copy($this->importDir.$fname, $this->exportDir.$fname);
echo 'FILE WRITTEN: '.$this->exportDir.$fname.'<br>';
			}
		}
	}
	
	function getRelativePath($from, $to, $ps = '/') {
		if ($to !== '' && $to[0] === '/') { return $to; }
		if (mb_ereg('^(https?\:)?\/\/', $to)) { return $to; }
		if ($from[0] === $ps) { $from = mb_strcut($from, 1); }
		$arFrom = explode($ps, $from);
		array_pop($arFrom);
		$arTo = explode($ps, $to);
		$toFile = array_pop($arTo);
		while(count($arFrom) && count($arTo) && ($arFrom[0] == $arTo[0])) {
			array_shift($arFrom);
			array_shift($arTo);
		}
		$toDir = str_pad('', count($arFrom)*3, '..'.$ps).implode($ps, $arTo);
		if ($toDir !== '' && substr($toDir, -1, 1) !== $ps) { $toDir .= $ps; }
		
		return $toDir.$toFile;
	}
	
	
	function files_are_equal($a, $b) {
		if (!file_exists($b)) { return false; }
		if (filesize($a) !== filesize($b)) { return false; }
		$ah = fopen($a, 'rb');
		$bh = fopen($b, 'rb');
		$result = true;
		while(!feof($ah)) {
			if (fread($ah, 8192) != fread($bh, 8192)) {
				$result = false;
				break;
			}
		}
		fclose($ah);
		fclose($bh);
		return $result;
	}
}


class ProtoPage extends ProtoSite {
	function __construct($proto) {
		foreach ($proto as $prop => $data) {
			$this->{$prop} = $data;
		}
	}
	
	
	
	function dataExtend() {
		$dir = $this->parent->importDir;
		if (!isset($this->use)) { return null; }
		foreach ($this->use as $use) {
			if (is_file($dir.$use)) {
				$this->code = mb_split('\n', file_get_contents($dir.$use));
			}
			elseif (isset($this->parent->pages[$use])) { 
				$copy = $this->parent->pages[$use];
				foreach ($copy as $prop => $value) {
					if (!isset($this->{$prop})) {
						$this->{$prop} = $value;
					}
				}
			}
		}
	}

	function templatePreserve() {
		foreach ($this as $prop => &$data) {
			if (!is_array($data)) { continue; }
			$code = implode("\n", $data);
			if (mb_ereg_search_init($code, '(<!--\s*)'.self::refPattern().'(.+?)(\s*-->)')) { 
				while(list($expr,,$ref,$pat,) = mb_ereg_search_regs()) {
					do{ $id = $ref.'_'.mt_rand(0,999999); }
					while(isset($this->{$id}));
					$this->{$id} = $pat;
					$code = mb_ereg_replace(preg_quote($expr), '%'.$id.'%', $code);
					
				}
			}
			
			$data = explode("\n", $code);
		}
	}
	function templateResolve() {
		foreach ($this as $prop => $data) {
			if (is_array($data)) { continue; }
			if (mb_ereg(self::refPattern().'_\d+$', '@'.$prop, $regs)) {
				$temp = [];
				$refs = mb_split('\,', $regs[1]);
//echo $regs[1].' '.$prop.'<br>';
				foreach ($refs as $ref) {
					foreach ($this->parent->pages as &$page) {
						if ($ref === 'all' || in_array($ref, $page->ref)) {
							$new = $data;
							$page->aliasReplace($new);
							$temp[] = $new;
						}
					}
				}
				$this->{$prop} = $temp;
			}
		}
	}

	function aliasResolve() {
		foreach ($this as $prop => &$data) {
			if (!is_array($data)) { continue; }
			foreach ($data as &$line) {
				$this->aliasReplace($line);
			}
		}
	}
	function aliasReplace(&$line) {
		if (mb_ereg_search_init($line, '(<!--\s*)?'. self::aliasPattern() .'(\s*-->)?')) { 
			while(list($expr,,$var,$sub,) = mb_ereg_search_regs()) {
				$var = mb_strtolower($var);
				if (!isset($this->{$var}) || !is_array($this->{$var})) { continue; }
				if ($sub === false) { // %VAR% (simple var)
					$sub = implode("\n", $this->{$var});
					if ($sub === '-') { $sub = ''; }
					$line = mb_ereg_replace(preg_quote($expr), $sub, $line);
				}
				elseif (mb_ereg('([0-9]+)(-)?([0-9]+)?', $sub, $regs)) {
					if ($regs[2] === false) { // %VAR[1]% (line in var)
						$int = intval($regs[1])-1;
						if (isset($this->{$var}[$int])) {
							$sub = $this->{$var}[$int];
						}
						else{
							$sub = '';
						}
						$line = mb_ereg_replace(preg_quote($expr), $sub, $line);
					}
					else{  // %VAR[1-2]% (range in var)
						if ($regs[3] === false) {
							$regs[3] = count($this->{$var});
						}
						$regs[1] = intval($regs[1]) - 1;
						$regs[3] = intval($regs[3]) - 1;
						$new = [];
						foreach ($this->{$var} as $int => $val) {
							if ($int<$regs[1] || $int>$regs[3]) { continue; }
							$sub = $this->{$var}[$int];
							$new[] = mb_ereg_replace(preg_quote($expr), $sub, $line);
						}
						$line = implode("\n", $new);
					}
				}
			}
		}
	}


	
	
	function linksResolve() {
		if (!isset($this->code)) { return null; }
		
		$code = &$this->code;
		if (mb_ereg_search_init($code, '(\<[^\<\>]+)(href=[\"\']?|src=[\"\']?|action=[\"\']?)('. self::refPattern() .')')) { 
			while(list($expr,,,$pat,$ref) = mb_ereg_search_regs()) {
				$ref = mb_strtolower($ref);
				if (!isset($this->parent->pages[$ref])) { continue; }
				if (!isset($this->parent->pages[$ref]->url)) { continue; }
				$url = $this->parent->pages[$ref]->url[0];
				$url = $this->getRelativePath($url, $this->parent->pages[$ref]->url[0]);
				if ($url === '') { $url = '/'.$url; } // || $url[0] !== '/'
				$code = mb_ereg_replace(preg_quote($pat), $url, $code);
			}
		}
		if (mb_ereg_search_init($code, '(<link[^<>]+href=|<[^<>]+src=|<meta property=[\"\']*og\:image[\"\']* content=|<\?php require )[\"\'\s]*([^\"\'>]+)')) { 
			while(list($expr,,$url) = mb_ereg_search_regs()) {
				if (mb_ereg('^(https?\:)?\/\/(?!'.preg_quote($this->parent->domainBase).')', $url)) { continue; }
				$code = mb_ereg_replace(preg_quote($url), $this->getRelativePath($this->url[0], $url), $code);
				$url = mb_ereg_replace('^(https?\:)?\/\/'.preg_quote($this->parent->domainBase), '', $url);
				if (!in_array($url, $this->subFiles)) { $this->subFiles[] = $url; }
//echo $url . '+++<br>';
			}
		}
	}
	

	
	function codeNormalize() {
		if (!isset($this->code)) { return null; }
		$this->code = implode("\n", $this->code);
		
		if (!isset($this->normalize) || !$this->normalize) { return null; }
		
		$this->code = mb_ereg_replace('<!--.+?-->', '', $this->code);
		$this->code = mb_ereg_replace(PHP_EOL ."\s*". PHP_EOL, PHP_EOL, $this->code);
		//$this->code = mb_ereg_replace(PHP_EOL, "", $this->code);
		$this->code = trim($this->code);
		
		
		//if (mb_strtolower(pathinfo($this->url[0], PATHINFO_EXTENSION)) !== 'html') { return; }
		
		//echo "HTML_parse: ". $this->url[0] . "<br>";
		
		$dom = new DOMDocument();
		$dom->loadHTML($this->code, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		$html = $dom->getElementsByTagName("html");
		if ($html) { $this->traverseNodes($html); }
		$dom->preserveWhiteSpace = false;
		$dom->normalizeDocument();
		$this->code = $dom->saveHTML();

	}
	
	function codeAppend() {
		if (isset($this->init)) {
			$this->code = implode("\n", $this->init)."\n".$this->code;
		}
	}
	function traverseNodes($nodeList) {
		for ($i = 0; $i < $nodeList->length; $i++) {
			if ($nodeList->item($i)->childNodes) { //echo $nodeList->item($i)->tagName . ', ';
				$this->traverseNodes($nodeList->item($i)->childNodes);
			}
			else{
				$nodeValue = $nodeList->item($i)->nodeValue;
				if ($nodeValue && $nodeList->item($i)->nodeType == 3) {
					$nodeValue = $this->textNormalize($nodeValue);
				}
				if ($nodeList->item($i)->nodeValue != $nodeValue) {
					$nodeList->item($i)->nodeValue = $nodeValue;
				}
			}
		}
	}
	function textNormalize($text) {
		
		$text = mb_ereg_replace(preg_quote('&nbsp;'), json_decode('"\u00A0"'), $text);
		$text = mb_ereg_replace(preg_quote('&ensp;'), json_decode('"\u2002"'), $text);
		$text = mb_ereg_replace(preg_quote('&emsp;'), json_decode('"\u2003"'), $text);
		$text = mb_ereg_replace(preg_quote('&thinsp;'), json_decode('"\u202F"'), $text);
		$text = mb_ereg_replace(preg_quote('&amp;'), '&', $text);
		
		// Russian language module
		$text = mb_ereg_replace('([А-Яа-я!?])\"+($|\s|[.,:;!?\)])', "\\1»\\2", $text);
		$text = mb_ereg_replace('(^|\s|\()\"+([А-Яа-я])', "\\1«\\2", $text);
		for ($j = 0; $j < 2; $j++) {
			$text = mb_eregi_replace('(^|\s|«)(во|об|со|на|по|от|из|за|для|при|над|под|из-за|из-под|но|не|ни|без|их|[А-Яа-я]) ([А-Яа-я0-9«]|\<|$)', "\\1\\2".json_decode('"\u00A0"')."\\3", $text);
		}
		
		$text = mb_eregi_replace("^[-–−—] ", "—".json_decode('"\u00A0"'), $text);
		$text = mb_eregi_replace("\s[-–−—] ", json_decode('"\u00A0"')."— ", $text);
		$text = mb_eregi_replace("(^|\d|\s)[-–−—](\d)", "\\1−\\2", $text);
		$text = mb_ereg_replace('(\d)\s?(%|₽)', "\\1".json_decode('"\u202F"')."\\2", $text);
		$text = mb_ereg_replace('(\d)\s?([А-Яа-я])', "\\1".json_decode('"\u00A0"')."\\2", $text);
		$text = mb_ereg_replace('(\d)\s(\d)', "\\1".json_decode('"\u00A0"')."\\2", $text);
		
		$text = mb_ereg_replace('(^|\s)(г|ул|д|стр|корп|кв)\.\s([А-Яа-я0-9])', "\\1\\2.".json_decode('"\u00A0"')."\\3", $text);
		
		$text = mb_ereg_replace('[ ]+', ' ', $text);
		return $text;
	}

	function show() {
		if (!isset($this->code)) { return null; }
		if (!isset($this->parent->importDir)) { return null; }
		$code = $this->code;
		if (mb_ereg_search_init($code, '(<link[^<>]+href=[\"\']?|<[^<>]+src=[\"\']?)([^\"\'>]+)')) { 
			while(list($expr,$tag,$url) = mb_ereg_search_regs()) {
				if (mb_ereg('^https?\:\/\/', $url)) { continue; }		
				$new_url = mb_ereg_replace('^(\.\.\/)+', '', $url);
				$code = mb_ereg_replace(preg_quote($expr), $tag.$this->parent->importDir.$new_url.'?ver='.rand(), $code);
			}
		}
		echo $code;
	}

}





?>