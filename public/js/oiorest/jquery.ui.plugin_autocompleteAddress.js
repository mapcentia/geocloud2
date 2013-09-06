/**
 * @author Tobias Hinnerup - Hinnerup Net A/S (2011-06-23)
 * @version 1.4
 * @see http://www.hinnerup.net/permanent/2011/06/23/autocomplete-med-ajax-paa-adresse-med-jquery-og-oio/
 **/
 
 (function($) {
	$.widget("ui.autocompleteAddress", {
		options: {
			streetNumber: "#streetnumber",
			zipCode : "#zipcode",
			city: "#city",
			maxSuggestions: 12,
			matchAnywhere: false,
			zipCodeService: {
				uri: function(zipCode) { return "http://geo.oiorest.dk/postnumre/" + zipCode + ".json"; },
				type: "jsonp",
				property: "navn",
			},
			/* Caller id can be overridden/specified - but please: Only do so after careful consideration */
			id: "$Id: jquery.ui.plugin_autocompleteAddress.js 3719 2011-08-22 09:59:18Z Tobias Hinnerup $", 
		},
		
		_create: function() {  
			var eZipCode = $(this.options.zipCode);
			var eCity = $(this.options.city);
			var eStreetNumber = $(this.options.streetNumber);
			var eStreetName = $(this.element);
			var streetNumbers = []; //Client-side cache of current streets numbers
			var maxSuggestions = this.options.maxSuggestions;
			var matchAnywhere = this.options.matchAnywhere;
			var service = this.options.zipCodeService;
			var id = this.options.id;
			var previousSearch = "";
			var missFactor = 1;
			
			if(eZipCode.length && eCity.length) {
				eZipCode.blur(function () {
					var zipCode = eZipCode.val();
					if(zipCode.length == 4) {
						var serviceArguments = {
							callerId: id,
						};
						$.ajax({
							url: service.uri(zipCode),
							dataType: service.type,
							data: serviceArguments,
							success: function( postdistrikt ) {
								if(postdistrikt.constructor === Array) postdistrikt = postdistrikt[0];
								eCity.val(postdistrikt[service.property]);
							}
						});
					}
					else {
						//Invalid zipcode -> unknown city name
						eCity.val("")
					}
				});
			}
			
			var streetNumberParser = {
				isNumber: function(n) {
					return !isNaN(parseFloat(n)) && isFinite(n);
				},
				
				parse: function(streetNum) {
					streetNumberParser.p1 = "";
					streetNumberParser.p2 = "";
					streetNumberParser.s = false;
					$.each(streetNum, function(k, v) {
						if (!streetNumberParser.s && streetNumberParser.isNumber(v)) {
							streetNumberParser.p1 += ("" + v); 
						}
						else {
							streetNumberParser.s = true;
							streetNumberParser.p2 += ("" + v);
						}
					});
					streetNumberParser.p1 = streetNumberParser.p1 == "" ? "0" : streetNumberParser.p1;
					return {
						numeric: parseInt(streetNumberParser.p1), 
						alphabetic: $.trim(streetNumberParser.p2),
					}
				},
				
				fromAddresses: function(data) {
					return $.map(data, function( adresse ) {
						return { 
							label: adresse.husnr,
							value: adresse.husnr,
							parts: streetNumberParser.parse(adresse.husnr) /* Used when sorting */,
						};
					}).sort(function (a, b) {
						//Sort these "logically" while handling things like 2a, 3b etc. (OIO sorts alphanumerically)
						return a.parts.numeric < b.parts.numeric ? -1 : a.parts.numeric > b.parts.numeric ? 1 : 
								a.parts.alphabetic < b.parts.alphabetic ? -1 : a.parts.alphabetic > b.parts.alphabetic ? 1 : 0;
					});
				},
			};
			
			function streetNumber() {
				var serviceUri = "http://geo.oiorest.dk/adresser.json";
				var serviceArguments = {
					maxantal: 250, //Go ahead - get'em all! 
					callerId: id,
				};
				
				var streetName = eStreetName.val();
				if(streetName.length > 2)  serviceArguments.vejnavn = streetName;
				
				if(eZipCode.length) {
					var zipCode = eZipCode.val();
					if(zipCode.length == 4) serviceArguments.postnr = zipCode;
				}

				$.ajax({
					url: serviceUri,
					dataType: "jsonp",
					data: serviceArguments,
					success: function( data ) {
						streetNumbers = streetNumberParser.fromAddresses(data); 
						eStreetNumber.focus();
						eStreetNumber.autocomplete("search");
					}
				});
				
				eStreetNumber.autocomplete({
					source: function(request, response) {
						//Remove matches that are not in the beginning of husnr and reduce count to maxSuggestions
						var pattern = new RegExp("^" + eStreetNumber.val(), "i");
						var data = $.map(streetNumbers, function( streetNumber ) {
							return pattern.test(streetNumber.value) ? streetNumber : null;
						}).slice(0, maxSuggestions); //*/
						
						response(data);
					},
					minLength: 0
				});
			}
			
			/* While typing, increase factor when result count (after filtering) is zero.
			 * A good example of benefit is "Hovedvejen" (try with and without factor).
			**/
			function calculateFactor(results, current, previous, factor) {
				var isTyping = current.indexOf(previous) > -1;
				var isBacking = previous.indexOf(current) > -1;
				return !(isTyping || isBacking) ? 1 : results == 0 ? factor + 3 : factor;
			}
			
			//eStreetName != input element -> zip/city usage scenario
			if(eStreetName[0].tagName == "INPUT") {
				eStreetName.autocomplete({
					source: function( request, response ) {
						var serviceUri = "http://geo.oiorest.dk/vejnavne.json";
						var serviceArguments = {
							/* Note: Custom filtering on matchAnywhere=false is likely to reduce effective result count */
							maxantal: matchAnywhere ? 
										maxSuggestions : 
										eStreetName.val().length < 4 ? 
											20*maxSuggestions*missFactor : 
											2*maxSuggestions*missFactor ,
							vejnavn: request.term,
							callerId: id,
						};
						
						if(eZipCode.length) {
							var zipCode = eZipCode.val();
							if(zipCode.length == 4) serviceArguments.postnr = zipCode;
						}
						
						$.ajax({
							url: serviceUri,
							dataType: "jsonp",
							data: serviceArguments,
							success: function( data ) {
								//Map (and filter) OIO object to label/value for jQuery autocomplete, then reduce count
								var currentSearch = eStreetName.val();
								var pattern = new RegExp("^" + currentSearch, "i");
								data = $.map(data, function( vej ) {
									return !matchAnywhere && !pattern.test(vej.navn) ? null : {
										label: vej.navn + " (" + vej.postnummer.nr + ")",
										value: vej.navn
									}
								}).slice(0, maxSuggestions).sort(function (a, b) {
									//Sort on streetname and postalcode (utilizing that they are merged in label)
									return a.label < b.label ? -1 : a.label == b.label ? 0 : 1; 
								}); //*/
								
								missFactor = calculateFactor(data.length, currentSearch, previousSearch, missFactor);
								previousSearch = currentSearch;

								response(data);
							}
						});
					},
					minLength: 2,
					change	: function( event, ui ) {
						if(ui.item) {
							if(eZipCode.length) {
								//Rely on label to carry zip... :-S
								var zipCode = ui.item.label.substring(ui.item.label.length-5, ui.item.label.length-1);
								if(eZipCode.val() != zipCode) {
									eZipCode.val(zipCode);
									eZipCode.blur();
								}
							}
							if(eStreetNumber.length) {
								streetNumber();	
							}
						}
					},
					open: function() {
						$( this ).removeClass( "ui-corner-all" ).addClass( "ui-corner-top" );
					},
					close: function() {
						$( this ).removeClass( "ui-corner-top" ).addClass( "ui-corner-all" );
					}
				});
			}
		},
		
		destroy: function() {
			var eZipCode = $(this.options.zipCode);
			var eStreetNumber = $(this.options.streetNumber);
			var eStreetName = $(this.element);
			
			eStreetName.autocomplete("destroy");
			if(eStreetNumber.length) eStreetNumber.autocomplete("destroy");
			if(eZipCode.length) eZipCode.unbind("blur");
		},
	});
})(jQuery);