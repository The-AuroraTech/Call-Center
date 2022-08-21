selectedIndices = new Array();

function singleClickSelect(field) {
	var bFound = false;
	var i = 0;
	/* Old code allowing use for multiple fields
	if(selectedIndices[f]==null) {
	selectedIndices[f]=new Array(0);
	}
	*/

	for(i = 0; i < selectedIndices.length; i++) {
		bFound = false;
		if(selectedIndices[i] == field.selectedIndex) {
			bFound = true;
			break;
		}
	}

	if(bFound) {
		alert(selectedIndices[i]);
		field.options[selectedIndices[i]].selected = false;
		selectedIndices[i] = -1;
	} else {
		selectedIndices[selectedIndices.length] = field.selectedIndex;
	}

	field.selectedIndex = -1;
	for(i = 0; i < selectedIndices.length; i++) {
		if(selectedIndices[i] > -1)
			field.options[selectedIndices[i]].selected = true;
	}
}
