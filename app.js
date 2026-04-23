let currentUser = null;
let currentMealType = 'petit-déjeuner';
let todayMeals = [];
let targets = {};
let analysisPeriod = 7;
const TODAY = new Date().toDateString();

function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach((b, i) => b.classList.toggle('active', (tab === 'login' && i === 0) || (tab === 'register' && i === 1)));
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
        sucEl.textContent = 'Compte créé ! Connexion en cours...';
        setTimeout(() => {
            document.getElementById('login-email').value = email;
            document.getElementById('login-pass').value = pass;
            login();
        }, 1000);
    } else {
        errEl.textContent = res.error || 'Erreur lors de l\'inscription';
    }
}

function goHome() {
    if (!currentUser) return;
    if (parseInt(currentUser.is_admin) === 1) {
        showScreen('admin');
        loadAdminUsers();
    } else {
        showScreen('dashboard');
        buildDashboard();
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
        
        if (currentUser.is_admin == 1 || currentUser.is_admin === true) {
            window.location.href = 'index_admin.html';
        } else {
            showScreen('dashboard');
            buildDashboard();
            updateNav();
        }
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
        currentUser.is_admin = res.is_admin;
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
    
    const isAdmin = parseInt(currentUser.is_admin) === 1;

    // Admin specific navigation
    if (isAdmin) {
        document.getElementById('nav-admin').style.display = 'block';
        document.getElementById('nav-dashboard').style.display = 'none';
        document.getElementById('nav-coach').style.display = 'none';
        document.getElementById('nav-trends').style.display = 'none';
        document.getElementById('nav-profile').style.display = 'none';
        document.getElementById('nav-community').style.display = 'block';
    } else {
        // User specific navigation
        document.getElementById('nav-admin').style.display = 'none';
        document.getElementById('nav-dashboard').style.display = 'block';
        document.getElementById('nav-community').style.display = 'block';
        document.getElementById('nav-coach').style.display = 'block';
        document.getElementById('nav-trends').style.display = 'block';
        document.getElementById('nav-profile').style.display = 'block';
    }
}

function buildProfileCard() {
    const u = currentUser.profile;
    if (!u) return;

    const bmiInfo = getBMICategory(u.bmi);

    document.getElementById('profile-info-card').innerHTML = `
        <div class="section-title" style="font-size:1rem">Informations personnelles</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;margin-top:1rem">
            ${infoRow('Nom complet', currentUser.prenom)}
            ${infoRow('Âge', u.age + ' ans')}
            ${infoRow('Taille', u.height_cm + ' cm')}
            ${infoRow('Poids', u.weight_kg + ' kg')}
            ${infoRow('Objectif', { maintain: 'Maintenir', lose: 'Perdre du poids', gain: 'Prendre du muscle' }[u.goal])}
            ${infoRow('Calories/jour', (currentUser.targets ? currentUser.targets.tdee_kcal : 0) + ' kcal')}
            ${u.medical_notes ? infoRow('Bilan santé', u.medical_notes, true) : ''}
        </div>`;

    document.getElementById('profile-bmi-card').innerHTML = `
        <div class="section-title" style="font-size:1rem">Indice de Masse Corporelle (IMC)</div>
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

function buildDashboard() {
    document.getElementById('dash-greeting').textContent = `Bonjour, ${currentUser.prenom} `;
    const now = new Date();
    document.getElementById('dash-date').textContent = now.toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

    if (targets.cal) {
        document.getElementById('stat-target').textContent = targets.cal;
        document.getElementById('mt-protein').textContent = `/ ${targets.protein}g`;
        document.getElementById('mt-carbs').textContent = `/ ${targets.carbs}g`;
        document.getElementById('mt-fat').textContent = `/ ${targets.fat}g`;
    }

    renderMeals();
    updateStats();
}

function selectMealType(btn, type) {
    document.querySelectorAll('.meal-type-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentMealType = type;
}

async function addFood() {
    const input = document.getElementById('food-input');
    const val = input.value.trim();
    if (!val) return;

    const btn = document.getElementById('add-btn');
    btn.disabled = true;
    btn.innerHTML = '<div class="loading-dots"><span></span><span></span><span></span></div>';

    const resp = await fetch('api/meals.php?action=add', {
        method: 'POST',
        body: JSON.stringify({ description: val, type: currentMealType })
    });
    const res = await resp.json();

    if (res.success) {
        input.value = '';
        todayMeals.unshift(res.new_meal);
        renderMeals();
        updateStats();
    }

    btn.disabled = false;
    btn.textContent = 'Ajouter';
}

function renderMeals() {
    const el = document.getElementById('meals-list');
    if (!el) return;
    if (todayMeals.length === 0) {
        el.innerHTML = '<div class="empty-state"><div class="ei">[REPAS]</div>Aucun aliment ajouté aujourd\'hui</div>';
        document.getElementById('analyze-btn').disabled = true;
        return;
    }
    document.getElementById('analyze-btn').disabled = false;
    el.innerHTML = todayMeals.map(m => `
    <div class="meal-item">
      <div class="meal-dot"></div>
      <div class="meal-info">
        <div class="meal-name">${m.food_description}</div>
        <div class="meal-meta">${m.meal_type} | P:${Math.round(m.protein_g)}g | G:${Math.round(m.carbs_g)}g | L:${Math.round(m.fat_g)}g</div>
      </div>
      <div class="meal-kcal">${Math.round(m.kcal)} kcal</div>
    </div>
  `).join('');
}

function updateStats() {
    if (!document.getElementById('stat-cal')) return;
    const totalCal = todayMeals.reduce((s, m) => s + parseFloat(m.kcal), 0);
    const totalP = todayMeals.reduce((s, m) => s + parseFloat(m.protein_g), 0);
    const totalC = todayMeals.reduce((s, m) => s + parseFloat(m.carbs_g), 0);
    const totalF = todayMeals.reduce((s, m) => s + parseFloat(m.fat_g), 0);

    document.getElementById('stat-cal').textContent = Math.round(totalCal);
    document.getElementById('stat-remaining').textContent = Math.max(0, Math.round((targets.cal || 0) - totalCal));
    document.getElementById('stat-meals').textContent = todayMeals.length;

    const pct = targets.cal ? Math.min(100, Math.round(totalCal / targets.cal * 100)) : 0;
    document.getElementById('cal-pct').textContent = pct + '%';
    const bar = document.getElementById('cal-bar');
    bar.style.width = pct + '%';
    bar.className = 'progress-fill' + (pct > 115 ? ' danger' : pct > 95 ? ' warn' : '');

    document.getElementById('m-protein').textContent = Math.round(totalP) + 'g';
    document.getElementById('m-carbs').textContent = Math.round(totalC) + 'g';
    document.getElementById('m-fat').textContent = Math.round(totalF) + 'g';

    if (targets.protein) document.getElementById('mb-protein').style.width = Math.min(100, Math.round(totalP / targets.protein * 100)) + '%';
    if (targets.carbs) document.getElementById('mb-carbs').style.width = Math.min(100, Math.round(totalC / targets.carbs * 100)) + '%';
    if (targets.fat) document.getElementById('mb-fat').style.width = Math.min(100, Math.round(totalF / targets.fat * 100)) + '%';
}

async function analyzeNutrition() {
    const btn = document.getElementById('analyze-btn');
    btn.disabled = true;
    btn.innerHTML = '<div class="loading-dots"><span></span><span></span><span></span></div> Analyse en cours...';

    const resp = await fetch('api/meals.php?action=analyze');
    const res = await resp.json();

    if (res.success) {
        const result = res.analysis;
        const emojiMap = { 'Suffisant': 'ok', 'Insuffisant': 'insuf', 'Excessif': 'excess', 'Trop gras': 'gras' };
        renderAnalysis({ ...result, emoji: emojiMap[result.status] || 'ok' });
    }

    btn.disabled = false;
    btn.innerHTML = 'Relancer l\'analyse';
}

function renderAnalysis(result) {
    const statusMap = { 'Suffisant': 'sufficient', 'Insuffisant': 'warning', 'Excessif': 'danger', 'Trop gras': 'danger' };
    const cls = statusMap[result.status] || 'warning';
    document.getElementById('analysis-result').innerHTML = `
    <div class="analysis-card ${cls}">
      <div class="analysis-icon">${result.emoji}</div>
      <div class="analysis-title">${result.title}</div>
      <div class="analysis-text">${result.summary}</div>
    </div>`;

    if (result.tips && result.tips.length) {
        document.getElementById('tips-card').style.display = '';
        document.getElementById('tips-list').innerHTML = result.tips.map(t => `<li>${t}</li>`).join('');
    }
    document.getElementById('analysis-result').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function showScreen(name) {
    document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
    document.getElementById('screen-' + name).classList.add('active');
    window.scrollTo(0, 0);
}

async function scanPhoto() {
    const btn = document.querySelector('.scanner-btn');
    btn.style.opacity = '0.5';
    btn.textContent = 'traitement...';

    const resp = await fetch('api/ai.php?action=scan_photo');
    const res = await resp.json();

    if (res.success) {
        document.getElementById('food-input').value = res.food;
        const inputEl = document.getElementById('food-input');
        inputEl.style.borderColor = 'var(--accent)';
        setTimeout(() => { inputEl.style.borderColor = ''; }, 2000);
    }

    btn.style.opacity = '1';
    btn.textContent = 'Scanner une photo';
}

async function sendMessage() {
    const input = document.getElementById('chat-input');
    const msg = input.value.trim();
    if (!msg) return;

    const sendBtn = document.querySelector('.btn-send');
    const container = document.getElementById('chat-messages');

    container.innerHTML += `<div class="user-msg">${msg}</div>`;
    input.value = '';
    input.disabled = true;
    sendBtn.disabled = true;
    container.scrollTop = container.scrollHeight;

    const typingId = 'typing-' + Date.now();
    container.innerHTML += `<div class="bot-msg" id="${typingId}" style="opacity:0.6">Coach en train d'écrire...</div>`;
    container.scrollTop = container.scrollHeight;

    try {
        const resp = await fetch('api/ai.php?action=chat', {
            method: 'POST',
            body: JSON.stringify({ message: msg })
        });
        const res = await resp.json();

        const typingEl = document.getElementById(typingId);
        if (res.success && res.reply) {
            typingEl.textContent = res.reply;
            typingEl.style.opacity = '1';
        } else {
            typingEl.innerHTML = `err <em>${res.error || 'Pas de réponse du serveur. Vérifiez qu\'Ollama est lancé.'}</em>`;
            typingEl.style.opacity = '0.8';
        }
    } catch (err) {
        const typingEl = document.getElementById(typingId);
        if (typingEl) typingEl.innerHTML = `err <em>Erreur réseau : ${err.message}</em>`;
    } finally {
        input.disabled = false;
        sendBtn.disabled = false;
        input.focus();
        container.scrollTop = container.scrollHeight;
    }
}

async function loadChatHistory() {
    const resp = await fetch('api/ai.php?action=get_chat_history');
    const res = await resp.json();
    if (res.success && res.history.length > 0) {
        const container = document.getElementById('chat-messages');
        container.innerHTML = res.history.map(h => `
      <div class="${h.role === 'user' ? 'user-msg' : 'bot-msg'}">${h.message}</div>
    `).join('');
    container.scrollTop = container.scrollHeight;
    }
}

// --- Admin Functions ---

async function loadAdminUsers() {
    const el = document.getElementById('admin-users-list');
    el.innerHTML = '<div class="loading-dots"><span></span><span></span><span></span></div> Chargement des utilisateurs...';
    
    const resp = await fetch('api/admin.php?action=get_users');
    const res = await resp.json();
    
    if (res.success) {
        if (res.users.length === 0) {
            el.innerHTML = '<div class="empty-state">Aucun utilisateur trouvé.</div>';
            return;
        }
        el.innerHTML = res.users.map(u => `
            <div class="user-card">
                <div class="user-card-header">
                    <strong>${u.prenom} ${u.nom}</strong>
                    <span class="user-email">${u.email}</span>
                </div>
                <div class="user-card-info">
                    <span>IMC: ${u.bmi || '--'}</span>
                    <span>Objectif: ${u.goal || '--'}</span>
                </div>
                <div class="user-card-actions">
                    <button class="btn-sm" onclick="viewUserHistory(${u.id}, '${u.prenom} ${u.nom}')">Historique</button>
                    <button class="btn-sm btn-danger" onclick="deleteUser(${u.id}, '${u.prenom} ${u.nom}')">Supprimer</button>
                </div>
            </div>
        `).join('');
    } else {
        el.innerHTML = `<div class="error-msg">${res.error}</div>`;
    }
}

async function deleteUser(userId, userName) {
    if (!confirm(`Êtes-vous sûr de vouloir supprimer l'utilisateur "${userName}" ? Cette action est irréversible.`)) {
        return;
    }
    
    const resp = await fetch(`api/admin.php?action=delete_user&user_id=${userId}`);
    const res = await resp.json();
    
    if (res.success) {
        loadAdminUsers();
        // Hide history if the deleted user was being viewed
        document.getElementById('user-history-section').style.display = 'none';
    } else {
        alert('Erreur: ' + res.error);
    }
}

async function viewUserHistory(userId, userName) {
    const section = document.getElementById('user-history-section');
    const el = document.getElementById('admin-user-history');
    document.getElementById('history-user-name').textContent = userName;
    
    section.style.display = 'block';
    el.innerHTML = '<div class="loading-dots"><span></span><span></span><span></span></div> Chargement de l\'historique...';
    section.scrollIntoView({ behavior: 'smooth' });

    const resp = await fetch(`api/admin.php?action=get_user_history&user_id=${userId}`);
    const res = await resp.json();
    
    if (res.success) {
        if (res.meals.length === 0) {
            el.innerHTML = '<div class="empty-state">Aucun aliment enregistré sur les 15 derniers jours.</div>';
            return;
        }
        
        // Group by date
        const grouped = {};
        res.meals.forEach(m => {
            if (!grouped[m.log_date]) grouped[m.log_date] = [];
            grouped[m.log_date].push(m);
        });
        
        el.innerHTML = Object.keys(grouped).map(date => `
            <div class="history-day">
                <div class="history-date">${new Date(date).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })}</div>
                <div class="history-meals">
                    ${grouped[date].map(m => `
                        <div class="history-meal-item">
                            <span>${m.meal_type}</span>
                            <strong>${m.food_description}</strong>
                            <span>${Math.round(m.kcal)} kcal</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `).join('');
    } else {
        el.innerHTML = `<div class="error-msg">${res.error}</div>`;
    }
}

// --- Community Functions ---

async function loadCommunity() {
    const el = document.getElementById('community-posts');
    el.innerHTML = '<div class="loading-dots"><span></span><span></span><span></span></div> Chargement...';
    
    const resp = await fetch('api/community.php?action=get_posts');
    const res = await resp.json();
    
    if (res.success) {
        if (res.posts.length === 0) {
            el.innerHTML = '<div class="empty-state">Aucun message partagé pour le moment.</div>';
            return;
        }
        el.innerHTML = res.posts.map(p => {
            const isDoc = parseInt(p.is_admin) === 1;
            const typeLabel = p.type === 'bilan' ? '📄 Bilan Médical' : '❓ Question';
            const userDisplay = isDoc ? `Dr. ${p.prenom}` : p.prenom;
            const cardClass = isDoc ? 'doctor-post' : 'user-post';
            
            const responsesHtml = (p.responses || []).map(r => `
                <div class="response-item ${parseInt(r.is_admin) === 1 ? 'doctor-response' : ''}">
                    <div class="response-header">
                        <span class="response-user">${parseInt(r.is_admin) === 1 ? 'Dr. ' : ''}${r.prenom}</span>
                        <span>${new Date(r.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}</span>
                    </div>
                    <div class="response-content">${r.content}</div>
                    ${r.image_path ? `<div class="response-image" style="margin-top:0.5rem"><img src="${r.image_path}" alt="Bilan" style="max-width:100%; border-radius:8px; cursor:pointer" onclick="window.open('${r.image_path}')"></div>` : ''}
                </div>
            `).join('');

            return `
                <div class="post-card ${cardClass}">
                    <div class="post-header">
                        <div class="post-user-info">
                            <div class="post-avatar">${userDisplay[0].toUpperCase()}</div>
                            <div>
                                <span class="post-user-name">
                                    ${userDisplay} ${isDoc ? '<span class="doctor-badge">Médecin</span>' : ''}
                                    ${p.recipient_name ? `<span class="recipient-tag">➔ Pour: ${p.recipient_name}</span> <span class="private-badge">Privé</span>` : '<span class="public-badge">Public</span>'}
                                </span>
                                <span class="post-date">${new Date(p.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}</span>
                            </div>
                        </div>
                        <span class="post-type-tag">${typeLabel}</span>
                    </div>
                    <div class="post-body">
                        <div class="post-title">${p.title}</div>
                        <div class="post-content">${p.content}</div>
                        ${p.image_path ? `<div class="post-image"><img src="${p.image_path}" alt="Bilan" onclick="window.open('${p.image_path}')"></div>` : ''}
                    </div>
                    <div class="responses-section">
                        <div class="responses-list">${responsesHtml}</div>
                        <div class="response-input-wrap" style="flex-direction:column; align-items:stretch">
                            <input type="text" class="response-input" id="resp-input-${p.id}" placeholder="Répondre...">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:0.5rem">
                                <input type="file" id="resp-file-${p.id}" style="font-size:0.8rem; flex:1">
                                <button class="btn-response" onclick="addResponse(${p.id})">Envoyer</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }
}

async function addResponse(postId) {
    const input = document.getElementById(`resp-input-${postId}`);
    const fileInput = document.getElementById(`resp-file-${postId}`);
    const content = input.value.trim();
    if (!content && !fileInput.files[0]) return;

    const formData = new FormData();
    formData.append('post_id', postId);
    formData.append('content', content);
    if (fileInput.files[0]) formData.append('image', fileInput.files[0]);

    try {
        const resp = await fetch('api/community.php?action=add_response', {
            method: 'POST',
            body: formData
        });
        const res = await resp.json();
        if (res.success) {
            loadCommunity();
        } else {
            alert('Erreur: ' + res.error);
        }
    } catch (err) {
        alert('Erreur réseau');
    }
}

async function showPostModal() {
    document.getElementById('post-modal').style.display = 'flex';
    
    // Show recipient select only for doctors
    const recipientGroup = document.getElementById('post-recipient-group');
    if (currentUser.is_admin) {
        recipientGroup.style.display = 'block';
        const resp = await fetch('api/community.php?action=get_users_list');
        const res = await resp.json();
        if (res.success) {
            const select = document.getElementById('post-recipient');
            select.innerHTML = '<option value="">Tout le monde (Public)</option>' + 
                res.users.map(u => `<option value="${u.id}">${u.prenom} ${u.nom}</option>`).join('');
        }
    } else {
        recipientGroup.style.display = 'none';
    }

    // Handle image input visibility
    document.getElementById('post-type').onchange = function () {
        document.getElementById('post-image-group').style.display = this.value === 'bilan' ? 'block' : 'none';
    };
}

function closePostModal() {
    document.getElementById('post-modal').style.display = 'none';
}

async function submitPost() {
    const type = document.getElementById('post-type').value;
    const title = document.getElementById('post-title').value.trim();
    const content = document.getElementById('post-content').value.trim();
    const recipientId = document.getElementById('post-recipient') ? document.getElementById('post-recipient').value : '';
    const imageFile = document.getElementById('post-image').files[0];
    
    if (!title || !content) {
        alert('Veuillez remplir le titre et le message.');
        return;
    }
    
    const formData = new FormData();
    formData.append('type', type);
    formData.append('title', title);
    formData.append('content', content);
    formData.append('recipient_id', recipientId);
    if (imageFile) formData.append('image', imageFile);
    
    const resp = await fetch('api/community.php?action=add_post', {
        method: 'POST',
        body: formData
    });
    const res = await resp.json();
    
    if (res.success) {
        closePostModal();
        loadCommunity();
        // Clear form
        document.getElementById('post-title').value = '';
        document.getElementById('post-content').value = '';
        document.getElementById('post-image').value = '';
    } else {
        alert('Erreur: ' + res.error);
    }
}

// --- Utils ---

const NUTRIENT_ICONS = {
    'Fer': 'Fe', 'Vitamine D': 'D', 'Magnésium': 'Mg', 'Calcium': 'Ca',
    'Zinc': 'Zn', 'Vitamine C': 'C', 'Vitamine B12': 'B12', 'Oméga-3': 'O3',
    'Potassium': 'K', 'Iode': 'I', 'Protéines': 'Proteines', 'Fibres': 'Fibres'
};

const DAY_EMOJIS = {
  'Lundi': 'L', 'Mardi': 'Ma', 'Mercredi': 'Me', 'Jeudi': 'J',
  'Vendredi': 'V', 'Samedi': 'S', 'Dimanche': 'D'
};

function setPeriod(btn, days) {
    analysisPeriod = days;
    document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

async function predictDeficiencies() {
    showScreen('trends');
    switchTrendTab(document.querySelector('.trend-tab'), 'deficiencies');

    const el = document.getElementById('deficiency-list');
    el.innerHTML = `
    <div class="deficiency-loading">
      <div class="loading-dots"><span></span><span></span><span></span></div>
      <span>Analyse sur ${analysisPeriod} jours en cours...</span>
    </div>`;

    const resp = await fetch(`api/ai.php?action=predict_deficiencies&period=${analysisPeriod}`);
    const res = await resp.json();

    if (res.success && res.data && res.data.length) {
        const trendIcon = { 'En baisse': 'baisse', 'En hausse': 'hausse', 'Stable': 'stable' };
        const riskClass = { 'Élevé': 'high', 'Moyen': 'medium', 'Faible': 'low' };
        el.innerHTML = res.data.map((d, i) => `
      <div class="deficiency-item" style="animation-delay:${i * 0.1}s">
        <div class="deficiency-left">
          <div class="deficiency-icon">${NUTRIENT_ICONS[d.nutrient] || ''}</div>
          <div class="deficiency-info">
            <div class="deficiency-nutrient">${d.nutrient}</div>
            <div class="deficiency-advice">${d.advice}</div>
          </div>
        </div>
        <div class="deficiency-right">
          <div class="deficiency-trend">${trendIcon[d.trend] || '--'} ${d.trend || 'Stable'}</div>
          <div class="deficiency-risk ${riskClass[d.risk] || 'low'}">Risque ${d.risk}</div>
        </div>
      </div>
    `).join('');
    } else {
        el.innerHTML = `<div class="empty-state"><div class="empty-icon">[OK]</div><div>Aucune carence détectée sur ${analysisPeriod} jours</div></div>`;
    }
}

async function generateMenu() {
    showScreen('trends');
    switchTrendTab(document.querySelectorAll('.trend-tab')[1], 'menus');

    const el = document.getElementById('menu-display');
    el.innerHTML = `
    <div class="deficiency-loading">
      <div class="loading-dots"><span></span><span></span><span></span></div>
      <span>Génération de votre plan repas...</span>
    </div>`;

    const resp = await fetch('api/ai.php?action=generate_menu');
    const res = await resp.json();

    if (res.success && res.menu) {
        const dayOrder = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        const days = dayOrder.filter(d => res.menu[d]).concat(
            Object.keys(res.menu).filter(d => !dayOrder.includes(d))
        );
        el.innerHTML = `<div class="menu-grid">${
            days.map((day, i) => {
                const meals = res.menu[day];
                return `
        <div class="menu-day-card" style="animation-delay:${i * 0.08}s">
          <div class="menu-day-header">
            <span class="menu-day-emoji">${DAY_EMOJIS[day] || 'aganda'}</span>
            <span class="menu-day-name">${day}</span>
          </div>
          <div class="menu-meal">
            <div class="menu-meal-label">Matin</div>
            <div class="menu-meal-text">${meals['Petit-déjeuner'] || '--'}</div>
          </div>
          <div class="menu-meal">
            <div class="menu-meal-label">Midi</div>
            <div class="menu-meal-text">${meals['Déjeuner'] || '--'}</div>
          </div>
          <div class="menu-meal">
            <div class="menu-meal-label">Soir</div>
            <div class="menu-meal-text">${meals['Dîner'] || '--'}</div>
          </div>
        </div>`;
            }).join('')
        }</div>`;
    } else {
        el.innerHTML = `<div class="empty-state"><div class="empty-icon">[ERR]</div><div>Erreur lors de la génération. Vérifiez qu'Ollama est actif.</div></div>`;
    }
}

function switchTrendTab(btn, tab) {
    document.querySelectorAll('.trend-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.trend-content').forEach(c => c.style.display = 'none');
    document.getElementById('trend-content-' + tab).style.display = '';
}

document.addEventListener('DOMContentLoaded', async () => {
    await loadUserData();
    if (currentUser && currentUser.prenom) {
        if (currentUser.is_admin == 1 || currentUser.is_admin === true) {
            window.location.href = 'index_admin.html';
        } else {
            showScreen('dashboard');
            buildDashboard();
            updateNav();
            loadChatHistory();
        }
    } else {
        showScreen('auth');
    }
});
