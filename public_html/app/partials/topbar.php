<?php
declare(strict_types=1);

$userName = header_user_value($u, 'name', 'usuário');
$userEmail = header_user_value($u, 'email');
$userRole = header_user_value($u, 'role');
$userSetor = header_user_value($u, 'setor');
$userHierarquia = header_user_value($u, 'hierarquia');
$avatarUrl = header_user_value($u, 'profile_photo_path');

$initials = header_build_initials($userName);
$greeting = header_get_greeting();

$notifUnread = 0;
$notifItems = [];

if (is_array($u) && isset($u['id'])) {
    $notifUnread = notifications_unread_count_for_user($u);
    $notifItems = notifications_latest_for_user($u, 5);
}

$adminItems = is_array($u) ? header_get_admin_items($u) : [];
$dashboardGroups = is_array($u) ? header_get_dashboard_groups($u) : [];

$coinsMenu = [
    ['label' => 'Meus Poppercoins', 'url' => '/coins/coins.php'],
    ['label' => 'Loja', 'url' => '/coins/coins_resgatar.php'],
    ['label' => 'Ranking', 'url' => '/coins/ranking.php'],
    ['label' => 'Campanhas', 'url' => '/coins/coins_campanhas.php'],
];
?>

<header class="topbar topbar--site">
    <div class="topbar__left">
        <a class="brand" href="/index.php">
            <?= header_e(defined('APP_NAME') ? (string) APP_NAME : 'Popper Conecta') ?>
        </a>

        <a class="link<?= $activePage === 'home' ? ' link--active' : '' ?>" href="/index.php">
            Início
        </a>

        <?php if (!empty($adminItems)): ?>
            <div class="topbar__dropdown js-hover-menu">
                <button
                    class="topbar__dropdown-trigger"
                    type="button"
                    aria-haspopup="true"
                    aria-expanded="false"
                    data-menu-toggle="adminMenu"
                >
                    Administração
                </button>

                <div class="topbar__dropdown-menu" id="adminMenu" role="menu">
                    <?php foreach ($adminItems as $item): ?>
                        <a class="topbar__dropdown-item" href="<?= header_e($item['url']) ?>">
                            <span class="topbar__dropdown-icon"><?= header_e($item['icon']) ?></span>
                            <span class="topbar__dropdown-label"><?= header_e($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($dashboardGroups)): ?>
            <div class="topbar__dropdown js-hover-menu" id="dashWrap">
                <button
                    class="topbar__dropdown-trigger"
                    type="button"
                    aria-haspopup="true"
                    aria-expanded="false"
                    data-menu-toggle="dashMenu"
                >
                    Dashboard
                </button>

                <div class="topbar__dropdown-menu topbar__dropdown-menu--wide" id="dashMenu" role="menu">
                    <?php foreach ($dashboardGroups as $group): ?>
                        <div class="topbar__dropdown-group">
                            <button
                                class="topbar__dropdown-item topbar__dropdown-item--group"
                                type="button"
                                aria-haspopup="true"
                                aria-expanded="false"
                            >
                                <span class="topbar__dropdown-label"><?= header_e($group['label']) ?></span>
                                <span class="topbar__dropdown-caret" aria-hidden="true">›</span>
                            </button>

                            <div class="topbar__dropdown-submenu" role="menu">
                                <?php foreach ($group['items'] as $item): ?>
                                    <a class="topbar__dropdown-item" href="<?= header_e($item['url']) ?>">
                                        <span class="topbar__dropdown-label"><?= header_e($item['label']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="topbar__dropdown js-hover-menu">
            <button
                class="topbar__dropdown-trigger<?= $activePage === 'coins' ? ' link--active' : '' ?>"
                type="button"
                aria-haspopup="true"
                aria-expanded="false"
                data-menu-toggle="coinsMenu"
            >
                Popper Coins
            </button>

            <div class="topbar__dropdown-menu" id="coinsMenu" role="menu">
                <?php foreach ($coinsMenu as $item): ?>
                    <a class="topbar__dropdown-item" href="<?= header_e($item['url']) ?>">
                        <span class="topbar__dropdown-label"><?= header_e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="topbar__right">
        <div class="notif" id="notifWrap">
            <button
                class="notif__btn"
                type="button"
                id="notifTrigger"
                aria-haspopup="true"
                aria-expanded="false"
                aria-controls="notifMenu"
                title="Notificações"
            >
                <span class="notif__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 1 0-14 0v5l-2 2v1h18v-1l-2-2Z"/>
                    </svg>
                </span>

                <?php if ($notifUnread > 0): ?>
                    <span class="notif__badge"><?= $notifUnread > 99 ? '99+' : (int) $notifUnread ?></span>
                <?php endif; ?>
            </button>

            <div class="notif__menu" id="notifMenu" role="menu" aria-label="Notificações">
                <div class="notif__header">
                    <div class="notif__title">Notificações</div>
                    <button type="button" class="notif__markall" id="notifMarkAll">Marcar todas</button>
                </div>

                <div class="notif__list">
                    <?php if (!$notifItems): ?>
                        <div class="notif__empty">Sem notificações.</div>
                    <?php else: ?>
                        <?php foreach ($notifItems as $n): ?>
                            <?php
                            $unread = ((int) ($n['is_read'] ?? 0) === 0);
                            $linkData = header_build_notification_href((string) ($n['link'] ?? ''));
                            ?>
                            <a
                                class="notif__item<?= $unread ? ' is-unread' : '' ?><?= $linkData['clickable'] ? '' : ' is-disabled' ?>"
                                href="<?= header_e($linkData['href']) ?>"
                                <?= $linkData['clickable'] ? '' : 'aria-disabled="true" tabindex="-1" onclick="return false;"' ?>
                                data-id="<?= (int) ($n['id'] ?? 0) ?>"
                            >
                                <div class="notif__item-title">
                                    <?= header_e((string) ($n['title'] ?? '')) ?>
                                </div>

                                <?php if (!empty($n['message'])): ?>
                                    <div class="notif__item-msg">
                                        <?= header_e((string) $n['message']) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="notif__item-date">
                                    <?= header_e((string) ($n['created_at'] ?? '')) ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="topbar__greeting" aria-label="Saudação">
            <?= header_e($greeting) ?>
            <span class="topbar__greeting-name">, <?= header_e($userName) ?>!</span>
        </div>

        <div class="profile" id="profileWrap">
            <button
                class="profile__btn"
                type="button"
                id="profileTrigger"
                aria-haspopup="true"
                aria-expanded="false"
                aria-controls="profileMenu"
            >
                <?php if ($avatarUrl !== ''): ?>
                    <img class="profile__avatar" src="<?= header_e($avatarUrl) ?>" alt="Foto de perfil">
                <?php else: ?>
                    <span class="profile__fallback" aria-hidden="true"><?= header_e($initials) ?></span>
                <?php endif; ?>
            </button>

            <div class="profile__menu" id="profileMenu" role="menu" aria-label="Menu do usuário">
                <div class="profile__header">
                    <div class="profile__name"><?= header_e($userName) ?></div>
                    <?php if ($userEmail !== ''): ?>
                        <div class="profile__email"><?= header_e($userEmail) ?></div>
                    <?php endif; ?>
                </div>

                <a class="profile__item" href="/me.php" role="menuitem">Meus dados</a>
                <a class="profile__item profile__item--danger" href="/logout.php" role="menuitem">Sair</a>
            </div>
        </div>
    </div>
</header>