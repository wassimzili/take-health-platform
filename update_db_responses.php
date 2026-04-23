<?php
require_once 'config/db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS post_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Table post_responses créée avec succès.";
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
