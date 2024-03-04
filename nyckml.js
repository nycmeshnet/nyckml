document.addEventListener('click', function() {
    // Send a message to the parent window
    window.parent.postMessage("switchTo", "https://dantonio.tech");
});
function goBack() {
    if (window.history && window.history.length > 1) {
        // If the iframe's history has more than one entry, go back
        window.history.back();
    } else {
        // If there's only one entry or no history, you might want to handle it differently
        console.log("Cannot go back within the iframe's history.");
    }
}
