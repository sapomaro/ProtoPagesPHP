# ProtoPages v0.5 
## PHP Static Site Generator & Template Builder

Create 'template.pp' text file (or several files with '*.pp' extension) in your 'import_folder'

Use the following syntax in '*.pp' files for templating pages:

    %REF% 	pageName, pageAlias, pageClass
    %URL% 	path/to/page/
    %CODE% 	contents of the page (if you use %CUSTOM_VAR% here, it will be replaced with 'custom value')
    %USE% 	template.html || %REF% of another page // <<< uses the code and props of another page
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
