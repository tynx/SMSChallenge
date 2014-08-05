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
		 "aoColumnDefs": [ { "bSortable": false, "aTargets": [8,9,10 ] } ],    
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


