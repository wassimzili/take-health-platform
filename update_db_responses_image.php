<?php
require_once 'config/db.php';
try {
    $pdo->exec("ALTER TABLE post_responses ADD COLUMN image_path VARCHAR(255) DEFAULT NULL");
    echo "Colonne image_path ajoutée avec succès à post_responses.";
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
