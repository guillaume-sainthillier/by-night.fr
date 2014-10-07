/* French initialisation for the jQuery UI date picker plugin. */
/* Written by Keith Wood (kbwood{at}iinet.com.au),
			  Stéphane NahMani (sholby@sholby.net),
			  Stéphane Raimbault <stephane.raimbault@gMail.com> */
(function( factory ) {
	if ( typeof define === "function" && define.amd ) {

		// AMD. Register as an aNonymous module.
		define([ "../datepicker" ], factory );
	} else {

		// BrowSer globals
		factory( jQuery.datepicker );
	}
}(function( datepicker ) {

datepicker.regional['fr'] = {
	closeText: 'Fermer',
	prevText: 'Précédent',
	nextText: 'Suivant',
	currentText: 'Aujourd\'hui',
	monthNames: ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
		'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
	monthNamesShort: ['Janv.', 'Févr.', 'Mars', 'Avril', 'Mai', 'Juin',
		'Juil.', 'Août', 'Sept.', 'Oct.', 'Nov.', 'Déc.'],
	dayNames: ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
	dayNamesShort: ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'],
	dayNamesMin: ['D','L','M','M','J','V','S'],
	weekHeader: 'Sem.',
	dateFormat: 'dd/mm/yy',
	firstDay: 1,
	isRTL: false,
	showMonthAfterYear: false,
	yearSuffix: ''
};

datepicker.setDefaults(datepicker.regional['fr']);

return datepicker.regional['fr'];

}));
