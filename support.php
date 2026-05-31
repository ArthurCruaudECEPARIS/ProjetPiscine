<?php
session_start();
require "config/database.php";
if (!isset($_SESSION['user_id'])) { header("Location: auth/login.php"); exit(); }

$user_id = $_SESSION['user_id'];
$success = "";
$error   = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $body    = trim($_POST['body'] ?? '');

    if (!$subject || !$body) {
        $error = "Veuillez remplir le sujet et le message.";
    } else {
        /* envoyer en notif aux admins (privilege >= 1) */
        $admins = $conn->query("SELECT id FROM users WHERE privilege >= 1");
        $label  = $category ? "[$category] " : "";
        while ($adm = $admins->fetch_assoc()) {
            create_notification($conn, $adm['id'], 'info',
                "🎫 Support — {$label}" . htmlspecialchars($subject) . " (de " . htmlspecialchars($_SESSION['username']) . ")",
                "panneau_moderation.php"
            );
        }
        /* confirmation à l'utilisateur */
        create_notification($conn, $user_id, 'info',
            "✅ Votre message de support a bien été envoyé. L'équipe vous répondra par notification.",
            "home.php?menu=Notifications"
        );
        $success = "Message envoyé ! Notre équipe vous répondra dans les plus brefs délais via vos notifications.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Support — Mercato Nova</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Nunito:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include "partials/header.php"; ?>

<div class="page-container" style="margin-top:30px;">
    <a href="home.php" class="back-link">← Retour</a>
    <div class="section-title">🎫 Support</div>
    <p class="section-sub">Une question, un problème ? Notre équipe est là pour vous aider.</p>

    <?php if ($success): ?>
    <div class="neon-card" style="text-align:center;padding:40px;margin-bottom:24px;">
        <div style="font-size:52px;margin-bottom:16px;">✅</div>
        <h2 style="font-family:'Rajdhani',sans-serif;color:var(--neon-green);">Message envoyé !</h2>
        <p style="color:var(--text-soft);margin-top:12px;"><?= htmlspecialchars($success) ?></p>
        <a href="home.php?menu=Notifications" class="neon-btn" style="display:inline-block;width:auto;padding:12px 24px;text-decoration:none;margin-top:20px;">Voir mes notifications</a>
    </div>
    <?php else: ?>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start;">

        <!-- Formulaire -->
        <form method="POST">
            <div class="neon-card" style="margin-bottom:16px;">
                <h2>Envoyer un message</h2>

                <div class="form-group">
                    <label class="form-label">Catégorie</label>
                    <select class="form-select" name="category">
                        <option value="">Choisir une catégorie</option>
                        <option value="Compte">🔑 Problème de compte</option>
                        <option value="Paiement">💰 Paiement / Porte-monnaie</option>
                        <option value="Achat">🛒 Achat / Commande</option>
                        <option value="Vente">📦 Vente / Produit</option>
                        <option value="Enchère">⚡ Enchère</option>
                        <option value="Négociation">🤝 Négociation</option>
                        <option value="Modération">🛡 Signalement / Modération</option>
                        <option value="Autre">❓ Autre</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Sujet *</label>
                    <input class="form-input" type="text" name="subject" placeholder="Résumez votre problème en une ligne" maxlength="200" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Message *</label>
                    <textarea class="form-input form-textarea" name="body" rows="6" placeholder="Décrivez votre problème en détail. Plus vous êtes précis, plus nous pourrons vous aider rapidement." required style="min-height:160px;"></textarea>
                </div>

                <button type="submit" class="neon-btn">📤 Envoyer le message</button>
            </div>
        </form>

        <!-- Sidebar FAQ -->
        <div style="display:flex;flex-direction:column;gap:16px;">

            <div class="neon-card" style="background:rgba(0,207,255,.04);border-color:rgba(0,207,255,.15);">
                <h2 style="font-size:15px;color:var(--neon-blue);margin-bottom:14px;">📖 FAQ rapide</h2>

                <details style="margin-bottom:12px;">
                    <summary style="color:white;cursor:pointer;font-weight:600;font-size:13px;list-style:none;display:flex;justify-content:space-between;align-items:center;">
                        Comment recharger mon solde ? <span style="color:var(--neon-blue);">+</span>
                    </summary>
                    <p style="color:var(--text-soft);font-size:13px;margin-top:8px;line-height:1.6;">
                        Rendez-vous dans <a href="porte_monnaie_view.php" style="color:var(--neon-blue);">Porte-monnaie</a> et utilisez le formulaire de recharge. Le paiement est simulé.
                    </p>
                </details>

                <details style="margin-bottom:12px;">
                    <summary style="color:white;cursor:pointer;font-weight:600;font-size:13px;list-style:none;display:flex;justify-content:space-between;align-items:center;">
                        Comment devenir vendeur ? <span style="color:var(--neon-blue);">+</span>
                    </summary>
                    <p style="color:var(--text-soft);font-size:13px;margin-top:8px;line-height:1.6;">
                        Remplissez le formulaire <a href="devenir_vendeur.php" style="color:var(--neon-blue);">Devenir vendeur</a>. Un administrateur validera votre demande.
                    </p>
                </details>

                <details style="margin-bottom:12px;">
                    <summary style="color:white;cursor:pointer;font-weight:600;font-size:13px;list-style:none;display:flex;justify-content:space-between;align-items:center;">
                        J'ai perdu une enchère, où est mon argent ? <span style="color:var(--neon-blue);">+</span>
                    </summary>
                    <p style="color:var(--text-soft);font-size:13px;margin-top:8px;line-height:1.6;">
                        Les fonds ne sont débités qu'à la fin de l'enchère si vous êtes le gagnant. Si votre solde est insuffisant à ce moment, la transaction est annulée.
                    </p>
                </details>

                <details style="margin-bottom:12px;">
                    <summary style="color:white;cursor:pointer;font-weight:600;font-size:13px;list-style:none;display:flex;justify-content:space-between;align-items:center;">
                        Comment signaler un utilisateur ? <span style="color:var(--neon-blue);">+</span>
                    </summary>
                    <p style="color:var(--text-soft);font-size:13px;margin-top:8px;line-height:1.6;">
                        Utilisez le formulaire ci-contre avec la catégorie "Signalement / Modération" en précisant le nom de l'utilisateur concerné.
                    </p>
                </details>

                <details>
                    <summary style="color:white;cursor:pointer;font-weight:600;font-size:13px;list-style:none;display:flex;justify-content:space-between;align-items:center;">
                        Comment supprimer mon compte ? <span style="color:var(--neon-blue);">+</span>
                    </summary>
                    <p style="color:var(--text-soft);font-size:13px;margin-top:8px;line-height:1.6;">
                        Contactez-nous via ce formulaire avec la catégorie "Problème de compte". Un administrateur traitera votre demande.
                    </p>
                </details>
            </div>

            <div class="neon-card" style="text-align:center;">
                <p style="color:var(--text-soft);font-size:13px;margin-bottom:8px;">Délai de réponse moyen</p>
                <p style="color:var(--neon-green);font-size:22px;font-weight:700;">~24h</p>
                <p style="color:var(--text-soft);font-size:12px;margin-top:4px;">Réponse par notification</p>
            </div>

        </div>
    </div>
    <?php endif; ?>
</div>

<?php include "partials/footer.php"; ?>
<style>
details summary::-webkit-details-marker { display:none; }
details[open] summary span { transform:rotate(45deg); display:inline-block; transition:.2s; }
</style>
</body>
</html>

