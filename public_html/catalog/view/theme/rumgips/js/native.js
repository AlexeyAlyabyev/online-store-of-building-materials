document.addEventListener('DOMContentLoaded', function() {
	if (sessionStorage.getItem("gips_banner") == false) document.querySelector(".new_gips").remove();

	document.querySelector(".new_gips .cross").onclick = function(){
		sessionStorage.setItem("gips_banner", 0);
		document.querySelector(".new_gips").remove();
	};
});