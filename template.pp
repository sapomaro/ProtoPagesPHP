%REF%		htaccess
%CODE%		ErrorDocument 404 http://mywebsite.com/404.html
%URL%		/.htaccess

%REF%		main, info, certain_pages
%USE%		template.html
%URL%		/
%FULLTITLE%	%TITLE%&ensp;|&ensp;%BRAND%
%TITLE%		Main Page
%BRAND%		MyWebSite
%KEYWORDS%	Key words
%DESC%		Description
%NAV%		<div>
				<a href="@main">Main Page</a>
				<a href="@page2">Page 2</a>
			</div>
%TEXT%		Main Page Header
			The text of the main page.
			More text.
%SET[|]%	One | Two | Three
%BODY_CODE%	<div>
				<h1>%TEXT[1]%</h1>
				<p>%TEXT[2-]%</p>
				<p>%SET[1]%, %SET[2]%, %SET[3]%</p>
			</div>
			<h2>List props of selected pages:</h2>
			<ul>
			<!--@certain_pages
				<li>%TITLE% %TITLE% %URL%</li>
			-->
			</ul>

%REF%		page2, certain_pages
%USE%		main
%URL%		page2.html
%TITLE%		Page 2
%TEXT%		The text of the page 2