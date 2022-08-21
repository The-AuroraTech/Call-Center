function zipInput(field) {
	if(field.value.length == 5) {
		$.get("/remote/postal_code.php", {zip: field.value}, zipQuery);
	}
}

function zipQuery(text) {
	parts = text.split('|');
	city = document.getElementById('city');
	city.value = parts[0];
	state = document.getElementById('state');
	state.value = parts[1];
}
