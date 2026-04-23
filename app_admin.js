// Global state
let currentUser = null;

// Navigation
function showScreen(name) {
    console.log('Switching to screen:', name);
    document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
    const screen = document.getElementById('screen-' + name);
    if (screen) {
        screen.classList.add('active');
        window.scrollTo(0, 0);
    }
}

// Admin Users Logic
async function loadAdminUsers() {
    const el = document.getElementById('admin-users-list');
    if (!el) return;
    el.innerHTML = '<div class="loading-dots"><span></span><span></span><span></span></div> Chargement des utilisateurs...';
    
    try {
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
                        <button class="btn-sm" onclick="viewUserHistory(${u.id}, '${u.prenom.replace(/'/g, "\\'")} ${u.nom.replace(/'/g, "\\'")}')">Historique</button>
                        <button class="btn-sm btn-danger" onclick="deleteUser(${u.id}, '${u.prenom.replace(/'/g, "\\'")} ${u.nom.replace(/'/g, "\\'")}')">Supprimer</button>
                    </div>
                </div>
            `).join('');
        } else {
            el.innerHTML = `<div class="error-msg">${res.error}</div>`;
        }
    } catch (err) {
        el.innerHTML = `<div class="error-msg">Erreur de chargement: ${err.message}</div>`;
    }
}

async function viewUserHistory(userId, userName) {
    const section = document.getElementById('user-history-section');
    const el = document.getElementById('admin-user-history');
    const nameEl = document.getElementById('history-user-name');
    
    if (nameEl) nameEl.textContent = userName;
    if (section) section.style.display = 'block';
    if (el) el.innerHTML = '<div class="loading-dots"><span></span><span></span><span></span></div> Chargement...';
    
    try {
        const resp = await fetch(`api/admin.php?action=get_user_history&user_id=${userId}`);
        const res = await resp.json();
        
        if (res.success) {
            if (res.meals.length === 0) {
                el.innerHTML = '<div class="empty-state">Aucun historique sur les 15 derniers jours.</div>';
                return;
            }
            
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
            if (section) section.scrollIntoView({ behavior: 'smooth' });
        } else {
            el.innerHTML = `<div class="error-msg">${res.error}</div>`;
        }
    } catch (err) {
        el.innerHTML = `<div class="error-msg">Erreur: ${err.message}</div>`;
    }
}

async function deleteUser(userId, userName) {
    if (!confirm(`Supprimer l'utilisateur "${userName}" ?`)) return;
    
    try {
        const resp = await fetch(`api/admin.php?action=delete_user&user_id=${userId}`);
        const res = await resp.json();
        if (res.success) {
            loadAdminUsers();
            document.getElementById('user-history-section').style.display = 'none';
        } else {
            alert('Erreur: ' + res.error);
        }
    } catch (err) {
        alert('Erreur réseau: ' + err.message);
    }
}

// Community Logic
async function loadCommunity() {
    const el = document.getElementById('community-posts');
    if (!el) return;
    el.innerHTML = '<div class="loading-dots"><span></span><span></span><span></span></div> Chargement...';
    
    try {
        const resp = await fetch('api/community.php?action=get_posts');
        const res = await resp.json();
        
        if (res.success) {
            if (res.posts.length === 0) {
                el.innerHTML = '<div class="empty-state">Aucun message.</div>';
                return;
            }
            el.innerHTML = res.posts.map(p => {
                const isDoc = parseInt(p.is_admin) === 1;
                const typeLabel = p.type === 'bilan' ? '📄 Bilan' : '❓ Question';
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
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span class="post-type-tag">${typeLabel}</span>
                                <button class="btn-sm btn-danger" onclick="deletePost(${p.id})" style="padding: 0.2rem 0.5rem; font-size: 0.8rem;">Supprimer</button>
                            </div>
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
    } catch (err) {
        el.innerHTML = `<div class="error-msg">Erreur: ${err.message}</div>`;
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

async function deletePost(postId) {
    if (!confirm('Voulez-vous vraiment supprimer cette conversation ?')) return;

    const formData = new FormData();
    formData.append('post_id', postId);

    try {
        const resp = await fetch('api/community.php?action=delete_post', {
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
    try {
        const resp = await fetch('api/community.php?action=get_users_list');
        const res = await resp.json();
        if (res.success) {
            const select = document.getElementById('post-recipient');
            select.innerHTML = '<option value="">Tout le monde (Public)</option>' + 
                res.users.map(u => `<option value="${u.id}">${u.prenom} ${u.nom}</option>`).join('');
        }
    } catch (e) {}

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
    const recipientId = document.getElementById('post-recipient').value;
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
    
    try {
        const resp = await fetch('api/community.php?action=add_post', {
            method: 'POST',
            body: formData
        });
        const res = await resp.json();
        if (res.success) {
            closePostModal();
            loadCommunity();
            document.getElementById('post-title').value = '';
            document.getElementById('post-content').value = '';
            document.getElementById('post-image').value = '';
        }
    } catch (e) {
        alert('Erreur lors de la publication');
    }
}

function logout() {
    fetch('api/auth.php?action=logout').then(() => {
        location.href = 'index.html';
    });
}

// Initialization
document.addEventListener('DOMContentLoaded', () => {
    console.log('Admin Interface Initializing...');
    fetch('api/auth.php?action=check')
        .then(r => r.json())
        .then(res => {
            if (res.success && (parseInt(res.user.is_admin) === 1 || res.user.is_admin === true)) {
                currentUser = res.user;
                document.getElementById('nav-name').textContent = res.user.prenom;
                document.getElementById('nav-avatar').textContent = res.user.prenom[0].toUpperCase();
                loadAdminUsers();
            } else {
                console.warn('Unauthorized access redirect');
                location.href = 'index.html';
            }
        })
        .catch(err => {
            console.error('Auth check failed:', err);
            location.href = 'index.html';
        });
});
