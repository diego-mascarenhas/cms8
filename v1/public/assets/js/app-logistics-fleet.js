!function(){var e=$(".logistics-fleet-sidebar-body");e.length&&new PerfectScrollbar(e[0],{wheelPropagation:!1,suppressScrollX:!0}),mapboxgl.accessToken="";const t={type:"FeatureCollection",features:[{type:"Feature",properties:{iconSize:[20,42],message:"1"},geometry:{type:"Point",coordinates:[-73.999024,40.75249842]}},{type:"Feature",properties:{iconSize:[20,42],message:"2"},geometry:{type:"Point",coordinates:[-74.03,40.75699842]}},{type:"Feature",properties:{iconSize:[20,42],message:"3"},geometry:{type:"Point",coordinates:[-73.967524,40.7599842]}},{type:"Feature",properties:{iconSize:[20,42],message:"4"},geometry:{type:"Point",coordinates:[-74.0325,40.742992]}}]},r=new mapboxgl.Map({container:"map",style:"mapbox://styles/mapbox/light-v9",center:[-73.999024,40.75249842],zoom:12.25});for(const e of t.features){const o=document.createElement("div"),s=e.properties.iconSize[0],a=e.properties.iconSize[1];o.className="marker",o.insertAdjacentHTML("afterbegin",'<img src="'+assetsPath+'img/illustrations/fleet-car.png" alt="Fleet Car" width="20" class="rounded-3" id="carFleet-'+e.properties.message+'">'),o.style.width=`${s}px`,o.style.height=`${a}px`,o.style.cursor="pointer",new mapboxgl.Marker(o).setLngLat(e.geometry.coordinates).addTo(r);const n=document.getElementById("fl-"+e.properties.message),c=document.getElementById("carFleet-"+e.properties.message);n.addEventListener("click",(function(){const o=document.querySelector(".marker-focus");Helpers._hasClass("active",n)?(r.flyTo({center:t.features[e.properties.message-1].geometry.coordinates,zoom:16}),o&&Helpers._removeClass("marker-focus",o),Helpers._addClass("marker-focus",c)):Helpers._removeClass("marker-focus",c)}))}const o=document.getElementById("carFleet-1");Helpers._addClass("marker-focus",o),document.querySelector(".mapboxgl-control-container").classList.add("d-none")}();
