"use strict";$((function(){var e=$(".datatables-order-details");if(e.length)e.DataTable({ajax:assetsPath+"json/ecommerce-order-details.json",columns:[{data:"id"},{data:"id"},{data:"product_name"},{data:"price"},{data:"qty"},{data:""}],columnDefs:[{className:"control",searchable:!1,orderable:!1,responsivePriority:2,targets:0,render:function(e,t,a,r){return""}},{targets:1,orderable:!1,checkboxes:{selectAllRender:'<input type="checkbox" class="form-check-input">'},render:function(){return'<input type="checkbox" class="dt-checkboxes form-check-input" >'},searchable:!1},{targets:2,responsivePriority:1,searchable:!1,orderable:!1,render:function(e,t,a,r){var s=a.product_name,n=a.product_info,o=a.image;if(o)var l='<img src="'+assetsPath+"img/products/"+o+'" alt="product-'+s+'" class="rounded-2">';else{var c=["success","danger","warning","info","dark","primary","secondary"][Math.floor(6*Math.random())],d=(s=a.product_name).match(/\b\w/g)||[];l='<span class="avatar-initial rounded-2 bg-label-'+c+'">'+(d=((d.shift()||"")+(d.pop()||"")).toUpperCase())+"</span>"}return'<div class="d-flex justify-content-start align-items-center text-nowrap"><div class="avatar-wrapper"><div class="avatar me-2">'+l+'</div></div><div class="d-flex flex-column"><h6 class="text-body mb-0">'+s+'</h6><small class="text-muted">'+n+"</small></div></div>"}},{targets:3,searchable:!1,orderable:!1,render:function(e,t,a,r){return"<span>$"+a.price+"</span>"}},{targets:4,searchable:!1,orderable:!1,render:function(e,t,a,r){return'<span class="text-body">'+a.qty+"</span>"}},{targets:5,searchable:!1,orderable:!1,render:function(e,t,a,r){return'<h6 class="mb-0">$'+a.qty*a.price+"</h6>"}}],order:[2,""],dom:"t",responsive:{details:{display:$.fn.dataTable.Responsive.display.modal({header:function(e){return"Details of "+e.data().full_name}}),type:"column",renderer:function(e,t,a){var r=$.map(a,(function(e,t){return""!==e.title?'<tr data-dt-row="'+e.rowIndex+'" data-dt-column="'+e.columnIndex+'"><td>'+e.title+":</td> <td>"+e.data+"</td></tr>":""})).join("");return!!r&&$('<table class="table"/><tbody />').append(r)}}}});setTimeout((()=>{$(".dataTables_filter .form-control").removeClass("form-control-sm"),$(".dataTables_length .form-select").removeClass("form-select-sm")}),300)})),function(){const e=document.querySelector(".delete-order");e&&(e.onclick=function(){Swal.fire({title:"Are you sure?",text:"You won't be able to revert order!",icon:"warning",showCancelButton:!0,confirmButtonText:"Yes, Delete order!",customClass:{confirmButton:"btn btn-primary me-2 waves-effect waves-light",cancelButton:"btn btn-label-secondary waves-effect waves-light"},buttonsStyling:!1}).then((function(e){e.value?Swal.fire({icon:"success",title:"Deleted!",text:"Order has been removed.",customClass:{confirmButton:"btn btn-success waves-effect waves-light"}}):e.dismiss===Swal.DismissReason.cancel&&Swal.fire({title:"Cancelled",text:"Cancelled Delete :)",icon:"error",customClass:{confirmButton:"btn btn-success waves-effect waves-light"}})}))});var t=(new Date).getFullYear();document.getElementById("orderYear").innerHTML=t}();