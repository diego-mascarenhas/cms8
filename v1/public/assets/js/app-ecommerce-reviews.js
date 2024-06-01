"use strict";!function(){let t,e,a,o;isDarkStyle?(t=config.colors_dark.cardColor,a=config.colors_dark.textMuted,o=config.colors_dark.headingColor,e="dark"):(t=config.colors.cardColor,a=config.colors.textMuted,o=config.colors.headingColor,e="");const r=document.querySelector("#reviewsChart"),s={chart:{height:160,width:190,type:"bar",toolbar:{show:!1}},plotOptions:{bar:{barHeight:"75%",columnWidth:"40%",startingShape:"rounded",endingShape:"rounded",borderRadius:5,distributed:!0}},grid:{show:!1,padding:{top:-25,bottom:-12}},colors:[config.colors_label.success,config.colors_label.success,config.colors_label.success,config.colors_label.success,config.colors.success,config.colors_label.success,config.colors_label.success],dataLabels:{enabled:!1},series:[{data:[20,40,60,80,100,80,60]}],legend:{show:!1},xaxis:{categories:["M","T","W","T","F","S","S"],axisBorder:{show:!1},axisTicks:{show:!1},labels:{style:{colors:a,fontSize:"13px"}}},yaxis:{labels:{show:!1}},responsive:[{breakpoint:0,options:{chart:{width:"100%"},plotOptions:{bar:{columnWidth:"40%"}}}},{breakpoint:1440,options:{chart:{height:150,width:190,toolbar:{show:!1}},plotOptions:{bar:{borderRadius:6,columnWidth:"40%"}}}},{breakpoint:1400,options:{plotOptions:{bar:{borderRadius:6,columnWidth:"40%"}}}},{breakpoint:1200,options:{chart:{height:130,width:190,toolbar:{show:!1}},plotOptions:{bar:{borderRadius:6,columnWidth:"40%"}}}},{breakpoint:992,chart:{height:150,width:190,toolbar:{show:!1}},options:{plotOptions:{bar:{borderRadius:5,columnWidth:"40%"}}}},{breakpoint:883,options:{plotOptions:{bar:{borderRadius:5,columnWidth:"40%"}}}},{breakpoint:768,options:{chart:{height:150,width:190,toolbar:{show:!1}},plotOptions:{bar:{borderRadius:4,columnWidth:"40%"}}}},{breakpoint:576,options:{chart:{width:"100%",height:"200",type:"bar"},plotOptions:{bar:{borderRadius:6,columnWidth:"30% "}}}},{breakpoint:420,options:{plotOptions:{chart:{width:"100%",height:"200",type:"bar"},bar:{borderRadius:3,columnWidth:"30%"}}}}]};if(void 0!==typeof r&&null!==r){new ApexCharts(r,s).render()}}(),$((function(){let t,e,a;isDarkStyle?(t=config.colors_dark.borderColor,e=config.colors_dark.bodyBg,a=config.colors_dark.headingColor):(t=config.colors.borderColor,e=config.colors.bodyBg,a=config.colors.headingColor);var o=$(".datatables-review"),r=baseUrl+"app/ecommerce/customer/details/overview",s={Pending:{title:"Pending",class:"bg-label-warning"},Published:{title:"Published",class:"bg-label-success"}};if(o.length){var i=o.DataTable({ajax:assetsPath+"json/app-ecommerce-reviews.json",columns:[{data:""},{data:"id"},{data:"product"},{data:"reviewer"},{data:"review"},{data:"date"},{data:"status"},{data:" "}],columnDefs:[{className:"control",searchable:!1,orderable:!1,responsivePriority:2,targets:0,render:function(t,e,a,o){return""}},{targets:1,orderable:!1,searchable:!1,responsivePriority:3,checkboxes:!0,render:function(){return'<input type="checkbox" class="dt-checkboxes form-check-input">'},checkboxes:{selectAllRender:'<input type="checkbox" class="form-check-input">'}},{targets:2,render:function(t,e,a,o){var r=a.product,s=a.company_name,i=a.id,n=a.product_image;if(n)var l='<img src="'+assetsPath+"img/ecommerce-images/"+n+'" alt="Product-'+i+'" class="rounded-2">';else{var c=["success","danger","warning","info","dark","primary","secondary"][Math.floor(6*Math.random())],d=(r=a.product).match(/\b\w/g)||[];l='<span class="avatar-initial rounded bg-label-'+c+'">'+(d=((d.shift()||"")+(d.pop()||"")).toUpperCase())+"</span>"}return'<div class="d-flex justify-content-start align-items-center customer-name"><div class="avatar-wrapper"><div class="avatar me-2 rounded-2 bg-label-secondary">'+l+'</div></div><div class="d-flex flex-column"><span class="fw-medium text-nowrap">'+r+'</span></a><small class="text-muted">'+s+"</small></div></div>"}},{targets:3,responsivePriority:1,render:function(t,e,a,o){var s=a.reviewer,i=a.email,n=a.avatar;if(n)var l='<img src="'+assetsPath+"img/avatars/"+n+'" alt="Avatar" class="rounded-circle">';else{var c=["success","danger","warning","info","dark","primary","secondary"][Math.floor(6*Math.random())],d=(s=a.reviewer).match(/\b\w/g)||[];l='<span class="avatar-initial rounded-circle bg-label-'+c+'">'+(d=((d.shift()||"")+(d.pop()||"")).toUpperCase())+"</span>"}return'<div class="d-flex justify-content-start align-items-center customer-name"><div class="avatar-wrapper"><div class="avatar me-2">'+l+'</div></div><div class="d-flex flex-column"><a href="'+r+'"><span class="fw-medium">'+s+'</span></a><small class="text-muted text-nowrap">'+i+"</small></div></div>"}},{targets:4,responsivePriority:2,sortable:!1,render:function(t,e,a,o){var r=a.review,s=a.head,i=a.para,n=$('<div class="read-only-ratings ps-0 mb-2"></div>');var l,c="string"!=typeof(l=s)||0===l.length?l:l.charAt(0).toUpperCase()+l.slice(1);return n.rateYo({rating:r,rtl:isRtl,readOnly:!0,starWidth:"20px",spacing:"3px",starSvg:'<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-star-filled" width="44" height="44" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8.243 7.34l-6.38 .925l-.113 .023a1 1 0 0 0 -.44 1.684l4.622 4.499l-1.09 6.355l-.013 .11a1 1 0 0 0 1.464 .944l5.706 -3l5.693 3l.1 .046a1 1 0 0 0 1.352 -1.1l-1.091 -6.355l4.624 -4.5l.078 -.085a1 1 0 0 0 -.633 -1.62l-6.38 -.926l-2.852 -5.78a1 1 0 0 0 -1.794 0l-2.853 5.78z" stroke-width="0" /></svg>'}),"<div>"+n.prop("outerHTML")+'<p class="h6 mb-1 text-truncate">'+c+'</p><small class="text-break pe-3">'+i+"</small></div>"}},{targets:5,render:function(t,e,a,o){return'<span class="text-nowrap">'+new Date(a.date).toLocaleDateString("en-US",{month:"short",day:"numeric",year:"numeric"})+"</span>"}},{targets:6,render:function(t,e,a,o){var r=a.status;return'<span class="badge '+s[r].class+'" text-capitalize>'+s[r].title+"</span>"}},{targets:-1,title:"Actions",searchable:!1,orderable:!1,render:function(t,e,a,o){return'<div class="text-xxl-center"><div class="dropdown"><a href="javascript:;" class="btn dropdown-toggle hide-arrow text-body p-0" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i></a><div class="dropdown-menu dropdown-menu-end"><a href="javascript:;" class="dropdown-item">Download</a><a href="javascript:;" class="dropdown-item">Edit</a><a href="javascript:;" class="dropdown-item">Duplicate</a><div class="dropdown-divider"></div><a href="javascript:;" class="dropdown-item delete-record text-danger">Delete</a></div></div></div>'}}],order:[[2,"asc"]],dom:'<"card-header d-flex align-items-md-center pb-md-2 flex-wrap"<"me-5 ms-n2"f><"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-end align-items-md-center justify-content-md-end pt-0 gap-2 flex-wrap"l<"review_filter"> <"mx-0 me-md-n3 mt-sm-0"B>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',language:{sLengthMenu:"_MENU_",search:"",searchPlaceholder:"Search Review"},buttons:[{extend:"collection",className:"btn btn-label-secondary dropdown-toggle ms-2 me-3 mt-2 mt-sm-0 waves-effect waves-light",text:'<i class="ti ti-download me-1"></i>Export',buttons:[{extend:"print",text:'<i class="ti ti-printer me-2" ></i>Print',className:"dropdown-item",exportOptions:{columns:[1,2,3,4,5,6],format:{body:function(t,e,a){if(t.length<=0)return t;var o=$.parseHTML(t),r="";return $.each(o,(function(t,e){void 0!==e.classList&&e.classList.contains("customer-name")?r+=e.lastChild.firstChild.textContent:void 0===e.innerText?r+=e.textContent:r+=e.innerText})),r}}},customize:function(o){$(o.document.body).css("color",a).css("border-color",t).css("background-color",e),$(o.document.body).find("table").addClass("compact").css("color","inherit").css("border-color","inherit").css("background-color","inherit")}},{extend:"csv",text:'<i class="ti ti-file me-2" ></i>Csv',className:"dropdown-item",exportOptions:{columns:[1,2,3,4,5,6],format:{body:function(t,e,a){if(t.length<=0)return t;var o=$.parseHTML(t),r="";return $.each(o,(function(t,e){void 0!==e.classList&&e.classList.contains("customer-name")?r+=e.lastChild.firstChild.textContent:void 0===e.innerText?r+=e.textContent:r+=e.innerText})),r}}}},{extend:"excel",text:'<i class="ti ti-file-export me-2"></i>Excel',className:"dropdown-item",exportOptions:{columns:[1,2,3,4,5,6],format:{body:function(t,e,a){if(t.length<=0)return t;var o=$.parseHTML(t),r="";return $.each(o,(function(t,e){void 0!==e.classList&&e.classList.contains("customer-name")?r+=e.lastChild.firstChild.textContent:void 0===e.innerText?r+=e.textContent:r+=e.innerText})),r}}}},{extend:"pdf",text:'<i class="ti ti-file-text me-2"></i>Pdf',className:"dropdown-item",exportOptions:{columns:[1,2,3,4,5,6],format:{body:function(t,e,a){if(t.length<=0)return t;var o=$.parseHTML(t),r="";return $.each(o,(function(t,e){void 0!==e.classList&&e.classList.contains("customer-name")?r+=e.lastChild.firstChild.textContent:void 0===e.innerText?r+=e.textContent:r+=e.innerText})),r}}}},{extend:"copy",text:'<i class="ti ti-copy me-2"></i>Copy',className:"dropdown-item",exportOptions:{columns:[1,2,3,4,5,6],format:{body:function(t,e,a){if(t.length<=0)return t;var o=$.parseHTML(t),r="";return $.each(o,(function(t,e){void 0!==e.classList&&e.classList.contains("customer-name")?r+=e.lastChild.firstChild.textContent:void 0===e.innerText?r+=e.textContent:r+=e.innerText})),r}}}}]}],responsive:{details:{display:$.fn.dataTable.Responsive.display.modal({header:function(t){return"Details of "+t.data().customer}}),type:"column",renderer:function(t,e,a){var o=$.map(a,(function(t,e){return""!==t.title?'<tr data-dt-row="'+t.rowIndex+'" data-dt-column="'+t.columnIndex+'"><td>'+t.title+":</td> <td>"+t.data+"</td></tr>":""})).join("");return!!o&&$('<table class="table"/><tbody />').append(o)}}},initComplete:function(){this.api().columns(6).every((function(){var t=this,e=$('<select id="Review" class="form-select"><option value=""> All </option></select>').appendTo(".review_filter").on("change",(function(){var e=$.fn.dataTable.util.escapeRegex($(this).val());t.search(e?"^"+e+"$":"",!0,!1).draw()}));t.data().unique().sort().each((function(t,a){e.append('<option value="'+t+'" class="text-capitalize">'+t+"</option>")}))}))}});$(".dataTables_length").addClass("mt-0 mt-md-3")}$(".datatables-review tbody").on("click",".delete-record",(function(){i.row($(this).parents("tr")).remove().draw()})),setTimeout((()=>{$(".dataTables_filter .form-control").removeClass("form-control-sm"),$(".dataTables_length .form-select").removeClass("form-select-sm")}),300)}));
