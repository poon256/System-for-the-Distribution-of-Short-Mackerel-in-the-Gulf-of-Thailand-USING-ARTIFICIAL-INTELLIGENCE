$( document ).tooltip();
$( "#datepicker" ).datepicker();
$( "#dialog" ).dialog();
new DataTable('#datatable');

function confirm_del(url)
{
	if (window.confirm("ต้องการเปลื่ยนสถานะหรือไม่"))
	{
		window.open(url,"_self");
	}
}