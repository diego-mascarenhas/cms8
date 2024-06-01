"use strict";$((function(){let e,t,a;isDarkStyle?(e=config.colors_dark.borderColor,t=config.colors_dark.bodyBg,a=config.colors_dark.headingColor):(e=config.colors.borderColor,t=config.colors.bodyBg,a=config.colors.headingColor);var s=$(".datatables-users"),n=$(".select2"),i=baseUrl+"app/user/view/account",r={1:{title:"Pending",class:"bg-label-warning"},2:{title:"Active",class:"bg-label-success"},3:{title:"Inactive",class:"bg-label-secondary"}};if(n.length){var o=n;o.wrap('<div class="position-relative"></div>').select2({placeholder:"Select Country",dropdownParent:o.parent()})}if(s.length)var l=s.DataTable({ajax:assetsPath+"json/user-list.json",columns:[{data:""},{data:"full_name"},{data:"role"},{data:"current_plan"},{data:"billing"},{data:"status"},{data:"action"}],columnDefs:[{className:"control",searchable:!1,orderable:!1,responsivePriority:2,targets:0,render:function(e,t,a,s){return""}},{targets:1,responsivePriority:4,render:function(e,t,a,s){var n=a.full_name,r=a.email,o=a.avatar;if(o)var l='<img src="'+assetsPath+"img/avatars/"+o+'" alt="Avatar" class="rounded-circle">';else{var c=["success","danger","warning","info","primary","secondary"][Math.floor(6*Math.random())],d=(n=a.full_name).match(/\b\w/g)||[];l='<span class="avatar-initial rounded-circle bg-label-'+c+'">'+(d=((d.shift()||"")+(d.pop()||"")).toUpperCase())+"</span>"}return'<div class="d-flex justify-content-start align-items-center user-name"><div class="avatar-wrapper"><div class="avatar me-3">'+l+'</div></div><div class="d-flex flex-column"><a href="'+i+'" class="text-body text-truncate"><span class="fw-medium">'+n+'</span></a><small class="text-muted">'+r+"</small></div></div>"}},{targets:2,render:function(e,t,a,s){var n=a.role;return"<span class='text-truncate d-flex align-items-center'>"+{Subscriber:'<span class="badge badge-center rounded-pill bg-label-warning w-px-30 h-px-30 me-2"><i class="ti ti-user ti-sm"></i></span>',Author:'<span class="badge badge-center rounded-pill bg-label-success w-px-30 h-px-30 me-2"><i class="ti ti-circle-check ti-sm"></i></span>',Maintainer:'<span class="badge badge-center rounded-pill bg-label-primary w-px-30 h-px-30 me-2"><i class="ti ti-chart-pie-2 ti-sm"></i></span>',Editor:'<span class="badge badge-center rounded-pill bg-label-info w-px-30 h-px-30 me-2"><i class="ti ti-edit ti-sm"></i></span>',Admin:'<span class="badge badge-center rounded-pill bg-label-secondary w-px-30 h-px-30 me-2"><i class="ti ti-device-laptop ti-sm"></i></span>'}[n]+n+"</span>"}},{targets:3,render:function(e,t,a,s){return'<span class="fw-medium">'+a.current_plan+"</span>"}},{targets:5,render:function(e,t,a,s){var n=a.status;return'<span class="badge '+r[n].class+'" text-capitalized>'+r[n].title+"</span>"}},{targets:-1,title:"Actions",searchable:!1,orderable:!1,render:function(e,t,a,s){return'<div class="d-flex align-items-center"><a href="javascript:;" class="text-body"><i class="ti ti-edit ti-sm me-2"></i></a><a href="javascript:;" class="text-body delete-record"><i class="ti ti-trash ti-sm mx-2"></i></a><a href="javascript:;" class="text-body dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical ti-sm mx-1"></i></a><div class="dropdown-menu dropdown-menu-end m-0"><a href="'+i+'" class="dropdown-item">View</a><a href="javascript:;" class="dropdown-item">Suspend</a></div></div>'}}],order:[[1,"desc"]],dom:'<"row me-2"<"col-md-2"<"me-3"l>><"col-md-10"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',language:{sLengthMenu:"_MENU_",search:"",searchPlaceholder:"Search.."},buttons:[{extend:"collection",className:"btn btn-label-secondary dropdown-toggle mx-3 waves-effect waves-light",text:'<i class="ti ti-screen-share me-1 ti-xs"></i>Export',buttons:[{extend:"print",text:'<i class="ti ti-printer me-2" ></i>Print',className:"dropdown-item",exportOptions:{columns:[1,2,3,4,5],format:{body:function(e,t,a){if(e.length<=0)return e;var s=$.parseHTML(e),n="";return $.each(s,(function(e,t){void 0!==t.classList&&t.classList.contains("user-name")?n+=t.lastChild.firstChild.textContent:void 0===t.innerText?n+=t.textContent:n+=t.innerText})),n}}},customize:function(s){$(s.document.body).css("color",a).css("border-color",e).css("background-color",t),$(s.document.body).find("table").addClass("compact").css("color","inherit").css("border-color","inherit").css("background-color","inherit")}},{extend:"csv",text:'<i class="ti ti-file-text me-2" ></i>Csv',className:"dropdown-item",exportOptions:{columns:[1,2,3,4,5],format:{body:function(e,t,a){if(e.length<=0)return e;var s=$.parseHTML(e),n="";return $.each(s,(function(e,t){void 0!==t.classList&&t.classList.contains("user-name")?n+=t.lastChild.firstChild.textContent:void 0===t.innerText?n+=t.textContent:n+=t.innerText})),n}}}},{extend:"excel",text:'<i class="ti ti-file-spreadsheet me-2"></i>Excel',className:"dropdown-item",exportOptions:{columns:[1,2,3,4,5],format:{body:function(e,t,a){if(e.length<=0)return e;var s=$.parseHTML(e),n="";return $.each(s,(function(e,t){void 0!==t.classList&&t.classList.contains("user-name")?n+=t.lastChild.firstChild.textContent:void 0===t.innerText?n+=t.textContent:n+=t.innerText})),n}}}},{extend:"pdf",text:'<i class="ti ti-file-code-2 me-2"></i>Pdf',className:"dropdown-item",exportOptions:{columns:[1,2,3,4,5],format:{body:function(e,t,a){if(e.length<=0)return e;var s=$.parseHTML(e),n="";return $.each(s,(function(e,t){void 0!==t.classList&&t.classList.contains("user-name")?n+=t.lastChild.firstChild.textContent:void 0===t.innerText?n+=t.textContent:n+=t.innerText})),n}}}},{extend:"copy",text:'<i class="ti ti-copy me-2" ></i>Copy',className:"dropdown-item",exportOptions:{columns:[1,2,3,4,5],format:{body:function(e,t,a){if(e.length<=0)return e;var s=$.parseHTML(e),n="";return $.each(s,(function(e,t){void 0!==t.classList&&t.classList.contains("user-name")?n+=t.lastChild.firstChild.textContent:void 0===t.innerText?n+=t.textContent:n+=t.innerText})),n}}}}]},{text:'<i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">Add New User</span>',className:"add-new btn btn-primary waves-effect waves-light",attr:{"data-bs-toggle":"offcanvas","data-bs-target":"#offcanvasAddUser"}}],responsive:{details:{display:$.fn.dataTable.Responsive.display.modal({header:function(e){return"Details of "+e.data().full_name}}),type:"column",renderer:function(e,t,a){var s=$.map(a,(function(e,t){return""!==e.title?'<tr data-dt-row="'+e.rowIndex+'" data-dt-column="'+e.columnIndex+'"><td>'+e.title+":</td> <td>"+e.data+"</td></tr>":""})).join("");return!!s&&$('<table class="table"/><tbody />').append(s)}}},initComplete:function(){this.api().columns(2).every((function(){var e=this,t=$('<select id="UserRole" class="form-select text-capitalize"><option value=""> Select Role </option></select>').appendTo(".user_role").on("change",(function(){var t=$.fn.dataTable.util.escapeRegex($(this).val());e.search(t?"^"+t+"$":"",!0,!1).draw()}));e.data().unique().sort().each((function(e,a){t.append('<option value="'+e+'">'+e+"</option>")}))})),this.api().columns(3).every((function(){var e=this,t=$('<select id="UserPlan" class="form-select text-capitalize"><option value=""> Select Plan </option></select>').appendTo(".user_plan").on("change",(function(){var t=$.fn.dataTable.util.escapeRegex($(this).val());e.search(t?"^"+t+"$":"",!0,!1).draw()}));e.data().unique().sort().each((function(e,a){t.append('<option value="'+e+'">'+e+"</option>")}))})),this.api().columns(5).every((function(){var e=this,t=$('<select id="FilterTransaction" class="form-select text-capitalize"><option value=""> Select Status </option></select>').appendTo(".user_status").on("change",(function(){var t=$.fn.dataTable.util.escapeRegex($(this).val());e.search(t?"^"+t+"$":"",!0,!1).draw()}));e.data().unique().sort().each((function(e,a){t.append('<option value="'+r[e].title+'" class="text-capitalize">'+r[e].title+"</option>")}))}))}});$(".datatables-users tbody").on("click",".delete-record",(function(){l.row($(this).parents("tr")).remove().draw()})),setTimeout((()=>{$(".dataTables_filter .form-control").removeClass("form-control-sm"),$(".dataTables_length .form-select").removeClass("form-select-sm")}),300)})),function(){const e=document.querySelectorAll(".phone-mask"),t=document.getElementById("addNewUserForm");e&&e.forEach((function(e){new Cleave(e,{phone:!0,phoneRegionCode:"US"})}));FormValidation.formValidation(t,{fields:{userFullname:{validators:{notEmpty:{message:"Please enter fullname "}}},userEmail:{validators:{notEmpty:{message:"Please enter your email"},emailAddress:{message:"The value is not a valid email address"}}}},plugins:{trigger:new FormValidation.plugins.Trigger,bootstrap5:new FormValidation.plugins.Bootstrap5({eleValidClass:"",rowSelector:function(e,t){return".mb-3"}}),submitButton:new FormValidation.plugins.SubmitButton,autoFocus:new FormValidation.plugins.AutoFocus}})}();
