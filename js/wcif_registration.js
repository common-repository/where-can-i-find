jQuery(document).ready(function(){
var errirHtml = '<strong>The information entered doesn\'t match our records</strong><br>perhaps you need to <strong>register</strong><br>or you have forgotten your <strong>password</strong><br>';	
		//handles the clicking on our Login button	
		jQuery('input[value="Login"]').click(function(event){
		event.preventDefault();
		jQuery('#error').empty();	
		var log = jQuery('[name="log"]').attr('value');
		var pwd = jQuery('[name="pwd"]').attr('value');
			if(jQuery('#rememberme').is(':checked')){
				var rememberme = true;
				}
				else {
				var rememberme = false;
				}
		//we don't want a redirect on erroneous login info so process it via ajax instead
		jQuery.post(wcif_ajax.aJaxURL, { action: "myajaxregistration", log: log, pwd: pwd, rememberme: rememberme}, function(xml){
			var stuff = jQuery(xml).find("response_data").text();
				if (stuff == "tehre was errir"){
					jQuery('#tab1_login').effect('shake',{times: 3, easing: 'easeInOutElastic'}, 100, function(){jQuery('[name="log"]').focus();jQuery('#error').append(errirHtml);});
					jQuery('[name="log"]').val('');
					jQuery('[name="pwd"]').val('');
					jQuery("#login-register-password a").click(function(){
						var activeTab = jQuery(this).find("a").attr("href");
						jQuery(activeTab).show();
					})
					}
					else{
						window.location.reload(true);
						}
					})
			})
		//handles the clicking on our password reset form
		jQuery('input[value="Reset my password"]').click(function(event){
			event.preventDefault();
			var postUrl = jQuery('#reset').attr('action');
			var user_login = jQuery('input[name="user_login"]:eq(1)').attr('value');
			var user_login = jQuery('#user_login_lost_password').attr('value');
			jQuery.post(postUrl, {action: "lostpassword", user_login: user_login}, function(response){
			//do stuff with response here
			jQuery('#reset_error').empty();
			var reset_errir = jQuery(response).find('#login_error').html();
				
					if(! reset_errir){
						reset_errir = 'Password reset, please check your email for confirmation link';
						jQuery('#tab3_login').empty().append(reset_errir);
						}
					else{
						jQuery('#tab3_login').effect('shake',{times: 3, easing: 'easeInOutElastic'}, 100, function(){jQuery('[name="user_login"]:eq(1)').focus();});
						jQuery('#reset_error').append(reset_errir);
						jQuery('[name="user_login"]').val('');

						}
			});
			})
		//handles clicking on our registration form
		jQuery('input[value="Sign up!"]').click(function(event){
			event.preventDefault();
			var postUrl = jQuery("#tab2_login>form").attr('action');
			var user_login = jQuery('input[name="user_login"]:eq(0)').attr('value');
			var user_email = jQuery('input[name="user_email"]:eq(0)').attr('value');
			
			jQuery.post(postUrl, {action:"register", user_login : user_login, user_email : user_email}, function(response){
				var reset_errir = jQuery(response).find('#login_error').html();
					if(! reset_errir){
						reset_errir = jQuery(response).find(".message").html();
						jQuery('#tab2_login').empty().append(reset_errir);
						}
						else{
							jQuery('#tab2_login').effect('shake',{times: 3, easing: 'easeInOutElastic'}, 100, function(){jQuery('[name="user_login"]:eq(0)').focus();});
							jQuery('#register_error').append(reset_errir);
							jQuery('[name="user_login"]').val('');
							jQuery('[name="user_email"]').val('');
							}
				})
		
		})
		//this is for the tabs layout		
		jQuery(".tab_content_login").hide();
		jQuery("ul.tabs_login li:first").addClass("active_login").show();
		jQuery(".tab_content_login:first").show();
		jQuery(".tabs_login li").click(function() {
			jQuery("ul.tabs_login li").removeClass("active_login");
			jQuery(this).addClass("active_login");
			jQuery(".tab_content_login").hide();
			var activeTab = jQuery(this).find("a").attr("href");
			if (jQuery.browser.msie) {jQuery(activeTab).show();}
			else {jQuery(activeTab).show();}
			return false;
		});
		
		
})
