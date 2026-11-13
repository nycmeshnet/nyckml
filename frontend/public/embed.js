// Only loaded by the embedded variant: tells the parent page to switch to this tool whenever it's clicked.
(function () {
	const parentOrigin = document.currentScript.dataset.parentOrigin;
	document.addEventListener('click', function () {
		window.parent.postMessage('switchTo', parentOrigin);
	});
})();
