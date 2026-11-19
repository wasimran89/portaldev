
/** 
	* Filename:     custom.js
	* Version:      1.0.0 (20 Sep 2019)
	* Website:      https://www.zymphonies.com
	* Description:  Global script
	* Author:		Zymphonies team
					info@zymphonies.com
**/

function themeMenu(){

	// Main menu
	jQuery('#main-menu').smartmenus();

	jQuery('.navbar-toggle').click(function(){
		jQuery('.main-container').toggleClass('expand-menu');
	});

	// Mobile dropdown menu
	if ( jQuery(window).width() < 767) {
		jQuery(".region-primary-menu li a:not(.has-submenu)").click(function () {
			jQuery('.region-primary-menu').hide();
		});
	}

}

function themeHome(){
	jQuery('.flexslider').flexslider({
    	animation: "slide"	
    });
}

jQuery(document).ready(function($){
	//Group logo
	/*
	var imgurl=Cookies.get('logourl'); //get the value from cookie /site/default
	if(imgurl)
	{
		var img = "https://devportal.jcius.com/"+imgurl; //baseurl
		//var imgurl = "https://devportal.jcius.com/sites/default/files/2022-12/contourlogo_0_0.png";
	    jQuery(".website-logo").attr("src",img);
	}
	*/
    //Group logo end
	themeMenu();
	themeHome();
});