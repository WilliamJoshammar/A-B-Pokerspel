// login.js - Tab-växling på inloggningssidan

function showTab(tab) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.auth-form').forEach(f => f.classList.add('hidden'));
    document.querySelector('#' + tab + '-form').classList.remove('hidden');
    event.target.classList.add('active');
}
