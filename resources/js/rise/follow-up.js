// Arriving at a point's #follow-up (from the Overview's "Plan a follow-up date") opens its date choices straight away
const choices = document.querySelector('#follow-up details');

if (choices && window.location.hash === '#follow-up') {
    choices.open = true;
}
