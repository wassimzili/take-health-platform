let currentUser = null;
let currentMealType = 'petit-déjeuner';
let todayMeals = [];
let targets = {};
let analysisPeriod = 7; // 7 or 30 days for deficiency analysis
const TODAY = new Date().toDateString();

function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach((b, i) =>
        b.classList.toggle('active', (tab === 'login' && i === 0) || (tab === 'register' && i === 1))
    );
    document.getElementById('tab-login').style.display = tab === 'login' ? '' : 'none';
    document.getElementById('tab-register').style.display = tab === 'register' ? '' : 'none';
}

async function register() {
    const prenom = document.getElementById('reg-prenom').value.trim();
    const nom = document.getElementById('reg-nom').value.trim();
    const email = document.getElementById('reg-email').value.trim().toLowerCase();
    const pass = document.getElementById('reg-pass').value;
    const age = parseInt(document.getElementById('reg-age').value);
    const height = parseInt(document.getElementById('reg-height').value);
    const weight = parseFloat(document.getElementById('reg-weight').value);
    const gender = document.getElementById('reg-gender').value;
    const activity = parseFloat(document.getElementById('reg-activity').value);
    const goal = document.getElementById('reg-goal').value;
    const medical = document.getElementById('reg-medical').value.trim();
    const errEl = document.getElementById('reg-error');
    const sucEl = document.getElementById('reg-success');

    errEl.textContent = '';
    sucEl.textContent = '';

    if (!prenom || !nom || !email || !pass || !age || !height || !weight) {
        errEl.textContent = 'Veuillez remplir tous les champs obligatoires.';
        return;
    }
    if (pass.length < 6) {
        errEl.textContent = 'Mot de passe trop court (min 6 caractères).';
        return;
    }

    const resp = await fetch('api/auth.php?action=register', {
        method: 'POST',
        body: JSON.stringify({ prenom, nom, email, pass, age, height, weight, gender, activity, goal, medical })
    });
    const res = await resp.json();

    if (res.success) {
        sucEl.textContent = '✓ Compte créé ! Connexion en cours...';
        setTimeout(() => {
            document.getElementById('login-email').value = email;
            document.getElementById('login-pass').value = pass;
            login();
        }, 1000);
    } else {
        errEl.textContent = res.error || 'Erreur lors de l\'inscription';
    }
}

async function login() {
    const email = document.getElementById('login-email').value.trim().toLowerCase();
    const pass = document.getElementById('login-pass').value;
    const errEl = document.getElementById('login-error');

    errEl.textContent = '';

    const resp = await fetch('api/auth.php?action=login', {
        method: 'POST',
        body: JSON.stringify({ email, pass })
    });
    const res = await resp.json();

    if (res.success) {
        currentUser = res.user;
        await loadUserData();
        showScreen('profile');
        updateNav();
        buildProfileCard();
    } else {
        errEl.textContent = res.error || 'Email ou mot de passe incorrect.';
    }
}

async function logout() {
    await fetch('api/auth.php?action=logout');
    currentUser = null;
    todayMeals = [];
    document.getElementById('main-nav').style.display = 'none';
    showScreen('auth');
}

async function loadUserData() {
    const resp = await fetch('api/profile.php');
    const res = await resp.json();

    if (res.success) {
        currentUser = currentUser || {};
        currentUser.prenom = res.user_name;
        currentUser.profile = res.profile;
        currentUser.targets = res.targets;
        todayMeals = res.meals || [];

        if (res.targets) {
            targets = {
                cal: res.targets.tdee_kcal,
                protein: res.targets.protein_g,
                carbs: res.targets.carbs_g,
                fat: res.targets.fat_g
            };
        }
    }
}

function updateNav() {
    document.getElementById('main-nav').style.display = 'flex';
    document.getElementById('nav-avatar').textContent = currentUser.prenom[0].toUpperCase();
    document.getElementById('nav-name').textContent = currentUser.prenom;
}

function buildProfileCard() {
    const u = currentUser.profile;
    if (!u) return;

    const bmiInfo = getBMICategory(u.bmi);

    document.getElementById('profile-info-card').innerHTML = `
        <div class="section-title" style="font-size:1rem"> Informations personnelles</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;margin-top:1rem">
            ${infoRow(' Nom complet', currentUser.prenom)}
            ${infoRow(' Âge', u.age + ' ans')}
            ${infoRow(' Taille', u.height_cm + ' cm')}
            ${infoRow(' Poids', u.weight_kg + ' kg')}
            ${infoRow(' Objectif', { maintain: 'Maintenir', lose: 'Perdre du poids', gain: 'Prendre du muscle' }[u.goal])}
            ${infoRow(' Calories/jour', (currentUser.targets ? currentUser.targets.tdee_kcal : 0) + ' kcal')}
            ${u.medical_notes ? infoRow(' Bilan santé', u.medical_notes, true) : ''}
        </div>`;

    document.getElementById('profile-bmi-card').innerHTML = `
        <div class="section-title" style="font-size:1rem"> Indice de Masse Corporelle (IMC)</div>
        <div class="bmi-display">
            <div class="bmi-val" style="color:${bmiInfo.color}">${u.bmi}</div>
            <div class="bmi-label">${bmiInfo.label}</div>
        </div>
        <div style="margin-top:1rem">
            <div style="font-size:0.82rem;color:var(--text2);line-height:1.6">${bmiInfo.advice}</div>
        </div>`;
}

function infoRow(label, val, full = false) {
    return `
        <div ${full ? 'style="grid-column:1/-1"' : ''}>
            <div style="font-size:0.75rem;color:var(--text3)">${label}</div>
            <div style="font-size:0.9rem;font-weight:500;margin-top:2px">${val}</div>
        </div>`;
}

function getBMICategory(bmi) {
    if (bmi < 18.5) return {
        label: 'Sous-poids',
        color: '#60a5fa',
        advice: 'Votre IMC indique une maigreur. Il est recommandé d\'augmenter votre apport calorique.'
    };
    if (bmi < 25) return {
        label: 'Poids normal ✓',
        color: '#4ade80',
        advice: 'Excellent ! Votre IMC est dans la plage normale.'
    };
    if (bmi < 30) return {
        label: 'Surpoids',
        color: '#fb923c',
        advice: 'Votre IMC indique un léger surpoids.'
    };
    return {
        label: 'Obésité',
        color: '#f87171',
        advice: 'Votre IMC indique une obésité.'
    };
}

function goToDashboard() {
    showScreen('dashboard');
    buildDashboard();
}