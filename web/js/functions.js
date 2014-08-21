// global variable to hold the selected users
var selected = [];

/*
 * onready:
 * configure the JQuery.dataTables appearence 
 *
 * overwrite standart config:
 * deactivate: pagination, lengthchange, autowith
 * activate: sorting and filter
 */
$(document).ready(function(){
	/* deactivate sorting on the last 3 columns ( view, edit, delete) on the usertable
	 */
	$('#usertable').dataTable({
		"bPaginate": false,
		"bLengthChange": false,
		"bAutoWidth": false,
		"bFilter": true,
		"bStateSave": true,
		"aaSorting":[],
		"aoColumnDefs": [ { "bSortable": false, "aTargets": [9,10,11 ] } ],
		"bProcessing": true,
	} );

	$('#usertable tbody').on('click', 'tr', function (){
		/* read out the specifig user !! 
		 * if you change the usertable, recheck if this is still ok 
		 * this requires that the username is in the first td
		 */
		var user_id = this.firstChild.textContent;

		// get the position of the user in the array
		var index = $.inArray(user_id, selected);

		if(index === -1 ){
			// if the user isn't in the selecte array, add him
			selected.push(user_id);
		}else{
			// if he is alreay in the array, remove him
			selected.splice(index, 1);
		}

		// set the class of the selected row to 'selected'
		$(this).toggleClass('selected');

		// show the buttons or hide them	
		if(selected.length > 0){
			document.getElementById("btnMultiple").style.visibility="visible";
		}else{
			document.getElementById("btnMultiple").style.visibility="hidden";
		}
	} );

	/* just activate filter
	 */
	$('#logtable').dataTable({
		"bPaginate": false,
		"bLengthChange": false,
		"bInfo": false,
		"bAutoWidth": false,
		"bStateSave": true,
		"bFilter": true,
		"aaSorting":[],
	} );

	/* deactivate filter 
	 */
	$('.noFilter').dataTable({
		"bPaginate": false,
		"bLengthChange": false,
		"bInfo": false,
		"bAutoWidth": false,
		"bFilter": false,
		"aaSorting":[],
	} );
});

/* deleteUser confirm popup
 */
function deleteUser(element, id, value){
	if(confirm('Delete "' + value + '"?')){
		$('#userid').val(id);
		$('#deleteform').submit();
	}
}

/* confirm the deletion of the permission of multiple users
 */
function deleteMultiple(element, id, value){
	if(confirm('Really delete all marked users?')){
		var all = '';

		for(userid in selected){
			all +=  selected[userid] + ';';
		}

		// remove last semi-colon
		all = all.slice(0, -1);

		$('#userid').val(all);
		$('#deleteform').submit();
	}
}

/* confirm the change of the permission of multiple users
 */
function changeMultiple(element, id, value){
 	if(confirm('Really change the permission of all marked users?')){
		var all = '';

		for(userid in selected){
			all +=  selected[userid] + ';';
		}

		// remove last semi-colon
		all = all.slice(0, -1);

		$('#useridPermi').val(all);
		$('#permissionform').submit();
	}
}
