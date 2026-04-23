<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, [], 'Non connecté');
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_posts':
        try {
            $userId = $_SESSION['user_id'];
            $isAdmin = $_SESSION['is_admin'] ?? false;

            if ($isAdmin) {
                // Admin sees everything
                $stmt = $pdo->query("SELECT cp.*, u.prenom, u.is_admin, r.prenom as recipient_name 
                                    FROM community_posts cp 
                                    JOIN users u ON cp.user_id = u.id 
                                    LEFT JOIN users r ON cp.recipient_id = r.id
                                    ORDER BY cp.created_at DESC");
            } else {
                // User sees public posts, their own posts, and posts directed to them
                $stmt = $pdo->prepare("SELECT cp.*, u.prenom, u.is_admin, r.prenom as recipient_name 
                                      FROM community_posts cp 
                                      JOIN users u ON cp.user_id = u.id 
                                      LEFT JOIN users r ON cp.recipient_id = r.id
                                      WHERE cp.recipient_id IS NULL 
                                         OR cp.user_id = ? 
                                         OR cp.recipient_id = ?
                                      ORDER BY cp.created_at DESC");
                $stmt->execute([$userId, $userId]);
            }
            $posts = $stmt->fetchAll();

            // Fetch responses for these posts
            foreach ($posts as &$post) {
                $stmtResp = $pdo->prepare("SELECT pr.*, u.prenom, u.is_admin 
                                          FROM post_responses pr 
                                          JOIN users u ON pr.user_id = u.id 
                                          WHERE pr.post_id = ? 
                                          ORDER BY pr.created_at ASC");
                $stmtResp->execute([$post['id']]);
                $post['responses'] = $stmtResp->fetchAll();
            }

            jsonResponse(true, ['posts' => $posts]);
        } catch (Exception $e) {
            jsonResponse(false, [], $e->getMessage());
        }
        break;

    case 'add_post':
        $userId = $_SESSION['user_id'];
        $type = $_POST['type'] ?? 'question';
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $recipientId = $_POST['recipient_id'] ?? null;
        if ($recipientId === 'null' || $recipientId === '') $recipientId = null;
        $imagePath = null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('bilan_') . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $imagePath = 'uploads/' . $filename;
            }
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO community_posts (user_id, recipient_id, type, title, content, image_path) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $recipientId, $type, $title, $content, $imagePath]);
            jsonResponse(true);
        } catch (Exception $e) {
            jsonResponse(false, [], $e->getMessage());
        }
        break;

    case 'add_response':
        $userId = $_SESSION['user_id'];
        $postId = $_POST['post_id'] ?? null;
        $content = $_POST['content'] ?? '';
        $imagePath = null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('resp_') . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $imagePath = 'uploads/' . $filename;
            }
        }

        if (!$postId || !$content) {
            jsonResponse(false, [], 'Données manquantes');
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO post_responses (post_id, user_id, content, image_path) VALUES (?, ?, ?, ?)");
            $stmt->execute([$postId, $userId, $content, $imagePath]);
            jsonResponse(true);
        } catch (Exception $e) {
            jsonResponse(false, [], $e->getMessage());
        }
        break;

    case 'get_users_list':
        try {
            $stmt = $pdo->query("SELECT id, prenom, nom FROM users WHERE is_admin = 0");
            $users = $stmt->fetchAll();
            jsonResponse(true, ['users' => $users]);
        } catch (Exception $e) {
            jsonResponse(false, [], $e->getMessage());
        }
        break;

    case 'delete_post':
        if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
            jsonResponse(false, [], 'Non autorisé');
        }
        $postId = $_POST['post_id'] ?? null;
        if (!$postId) {
            jsonResponse(false, [], 'ID du post manquant');
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM post_responses WHERE post_id = ?");
            $stmt->execute([$postId]);
            
            $stmt = $pdo->prepare("DELETE FROM community_posts WHERE id = ?");
            $stmt->execute([$postId]);
            
            jsonResponse(true);
        } catch (Exception $e) {
            jsonResponse(false, [], $e->getMessage());
        }
        break;

    default:
        jsonResponse(false, [], 'Action inconnue');
        break;
}
