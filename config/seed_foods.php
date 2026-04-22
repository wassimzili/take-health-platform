<?php
require_once __DIR__ . '/db.php';
try {
    $existing = (int)$pdo->query("SELECT COUNT(*) FROM food_items")->fetchColumn();

    if ($existing > 0) {
        $pdo->exec("DELETE FROM food_items");
        echo " Anciens aliments supprimés ($existing lignes)\n\n";
    }

    $foods = [
        ['Œuf brouillé',          'petit-dejeuner', '100g',  155, 13.6,  1.1, 11.0,  0.0, 'Vitamine D, B12',   'Fer, Choline'],
        ['Avoine nature',          'petit-dejeuner', '100g',  389, 16.9, 66.3,  6.9, 10.6, 'Vitamine B1',       'Manganèse, Phosphore'],
        ['Pain complet',           'petit-dejeuner', '100g',  265,  8.7, 49.2,  3.3,  6.3, 'Vitamines B',       'Fer, Magnésium'],
        ['Yaourt grec nature',     'petit-dejeuner', '100g',   59, 10.0,  3.3,  0.4,  0.0, 'Vitamine B12',      'Calcium, Probiotiques'],
        ['Fromage blanc 0%',       'petit-dejeuner', '100g',   45,  8.0,  4.0,  0.2,  0.0, 'Vitamine B2',       'Calcium, Phosphore'],
        ['Banane',                 'petit-dejeuner', '100g',   89,  1.1, 22.8,  0.3,  2.6, 'Vitamine B6, C',    'Potassium, Magnésium'],
        ['Lait demi-écrémé',       'petit-dejeuner', '200ml',  95,  6.8, 10.0,  3.5,  0.0, 'Vitamine D, B12',   'Calcium, Potassium'],
        ['Flocons d\'avoine',      'petit-dejeuner', '80g',   312, 10.7, 55.7,  5.1,  8.0, 'Vitamine B1',       'Fer, Zinc'],
        ['Poulet grillé',          'dejeuner',       '150g',  248, 46.5,  0.0,  5.4,  0.0, 'Vitamine B6, B3',   'Phosphore, Sélénium'],
        ['Lentilles cuites',       'dejeuner',       '150g',  173, 13.5, 30.0,  0.6,  7.9, 'Acide folique',     'Fer, Potassium'],
        ['Riz complet cuit',       'dejeuner',       '150g',  166,  3.9, 34.6,  1.5,  1.8, 'Vitamine B1',       'Magnésium, Phosphore'],
        ['Brocoli vapeur',         'dejeuner',       '150g',   51,  4.2,  6.6,  0.6,  2.6, 'Vitamine C, K',     'Calcium, Potassium'],
        ['Saumon grillé',          'dejeuner',       '150g',  309, 33.2,  0.0, 19.5,  0.0, 'Vitamine D, B12',   'Oméga-3, Sélénium'],
        ['Tofu ferme',             'dejeuner',       '150g',  144, 17.3,  3.5,  8.7,  0.3, 'Isoflavones',       'Calcium, Magnésium'],
        ['Quinoa cuit',            'dejeuner',       '150g',  180,  6.7, 31.5,  2.9,  2.8, 'Vitamine B2',       'Manganèse, Phosphore'],
        ['Épinards sautés',        'dejeuner',       '100g',   23,  2.9,  3.6,  0.4,  2.2, 'Vitamine K, A',     'Fer, Calcium'],
        ['Thon en boîte',          'dejeuner',       '100g',  132, 28.0,  0.0,  1.5,  0.0, 'Vitamine D, B12',   'Sélénium, Iode'],
        ['Dinde émincée',          'dejeuner',       '150g',  228, 43.5,  0.0,  5.1,  0.0, 'Vitamine B6',       'Phosphore, Sélénium'],
        ['Pâtes complètes cuites', 'dejeuner',       '200g',  280, 11.0, 54.0,  1.4,  6.0, 'Vitamines B',       'Fer, Magnésium'],
        ['Patate douce cuite',     'dejeuner',       '150g',  130,  2.3, 30.9,  0.1,  3.8, 'Vitamine A, C',     'Potassium, Manganèse'],
        ['Poisson vapeur',         'diner',           '150g', 165, 30.0,  0.0,  3.5,  0.0, 'Vitamine D, B12',   'Sélénium, Phosphore'],
        ['Soupe de légumes',       'diner',           '300ml', 90,  3.5, 15.0,  2.0,  4.0, 'Vitamine A, C',     'Potassium, Magnésium'],
        ['Omelette 2 œufs',        'diner',           '120g', 186, 15.6,  1.2, 13.6,  0.0, 'Vitamine D, B12',   'Fer, Choline'],
        ['Amandes',                'collation',       '30g',  173,  6.4,  4.5, 15.0,  2.1, 'Vitamine E',        'Magnésium, Calcium'],
        ['Pomme',                  'collation',       '150g',  78,  0.4, 20.7,  0.2,  3.6, 'Vitamine C',        'Potassium'],
        ['Noix',                   'collation',       '30g',  196,  4.6,  3.9, 19.6,  2.0, 'Vitamine E',        'Oméga-3, Magnésium'],
        ['Cottage cheese',         'collation',       '100g',  98, 11.1,  3.4,  4.3,  0.0, 'Vitamine B12',      'Calcium, Sélénium'],
        ['Houmous',                'collation',       '80g',  176,  7.7, 12.3, 10.1,  3.6, 'Vitamine B6',       'Fer, Folate'],
        ['Chocolat noir 70%',      'collation',       '30g',  172,  2.5, 13.1, 12.0,  2.7, 'Vitamine E',        'Magnésium, Fer'],
        ['Kiwi',                   'collation',       '100g',  61,  1.1, 14.7,  0.5,  3.0, 'Vitamine C, K',     'Potassium, Folate'],
    ];

    $stmt = $pdo->prepare("INSERT INTO food_items
        (name, category, portion_size, kcal_per_portion, protein_g, carbs_g, fat_g, fiber_g, vitamins, minerals, is_healthy)
        VALUES (?,?,?,?,?,?,?,?,?,?,1)");

    foreach ($foods as $f) {
        $stmt->execute($f);
    }

    echo "insertion avec succès " . count($foods) . " aliments insérés avec succès dans food_items !\n";
    echo "\n→ <a href='/take_health/'>Retour à l'application</a>";

} catch (PDOException $e) {
    echo " Erreur : " . htmlspecialchars($e->getMessage());
    echo "\n→ Assurez-vous d'avoir d'abord créé les tables via <a href='setup_db.php'>setup_db.php</a>";
}
?>
