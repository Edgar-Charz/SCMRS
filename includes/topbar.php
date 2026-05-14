<?php
$_topbarRole = $_SESSION['user_role'] ?? 'student';
$_topbarName = htmlspecialchars($_SESSION['username'] ?? 'User');
$_topbarAvatar = strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1));

$_roleConfig = [
    'admin' => ['label' => 'Administrator', 'icon' => 'fa-user-shield'],
    'staff' => ['label' => 'Staff Member', 'icon' => 'fa-user-tie'],
    'student' => ['label' => 'Student', 'icon' => 'fa-user-graduate'],
];
$_rc = $_roleConfig[$_topbarRole] ?? $_roleConfig['student'];
?>

<!-- Topbar -->
<nav class="navbar navbar-expand-lg navbar-dark custom-nav">
    <button id="sidebarCollapse" class="btn btn-dark ms-2">
        <i class="fas fa-list"></i>
    </button>

    <div class="container-fluid">
        <div class="dropdown ms-auto">
            <a href="#" class="d-flex align-items-center gap-2 text-white text-decoration-none dropdown-toggle"
                id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.25);
                       border-radius: 50px; padding: 5px 12px 5px 6px; transition: background 0.2s;"
                onmouseover="this.style.background='rgba(255,255,255,0.22)'"
                onmouseout="this.style.background='rgba(255,255,255,0.12)'">
                <span
                    class="rounded-circle d-inline-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                    style="width:30px; height:30px; background: rgba(255,255,255,0.3); font-size: 0.8rem;">
                    <?= $_topbarAvatar ?>
                </span>
                <span class="d-none d-sm-inline" style="font-size: 0.875rem; max-width: 120px; overflow: hidden;
                      text-overflow: ellipsis; white-space: nowrap;">
                    <?= $_topbarName ?>
                </span>
            </a>

            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 py-0" aria-labelledby="profileDropdown"
                style="min-width: 230px; border-radius: 10px; overflow: hidden;">

                <!-- Identity header -->
                <li class="px-3 py-3" style="background: linear-gradient(135deg, #1e3a5f, #2d6a9f);">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0"
                            style="width:42px; height:42px; background: rgba(255,255,255,0.2); font-size: 1.1rem;">
                            <?= $_topbarAvatar ?>
                        </div>
                        <div class="overflow-hidden">
                            <div class="text-white fw-semibold text-truncate" style="font-size: 0.9rem;">
                                <?= $_topbarName ?>
                            </div>
                            <span class="badge mt-1"
                                style="background: rgba(255,255,255,0.25); font-size: 0.7rem; letter-spacing: 0.5px;">
                                <i class="fas <?= $_rc['icon'] ?> me-1"></i><?= $_rc['label'] ?>
                            </span>
                        </div>
                    </div>
                </li>

                <li>
                    <hr class="dropdown-divider my-0">
                </li>

                <li>
                    <a class="dropdown-item d-flex align-items-center py-2 px-3" href="profile.php">
                        <span class="me-2 text-center" style="width:20px;">
                            <i class="fas fa-user-circle text-primary"></i>
                        </span>
                        My Profile
                    </a>
                </li>

                <li>
                    <hr class="dropdown-divider my-0">
                </li>

                <li>
                    <a class="dropdown-item d-flex align-items-center py-2 px-3 text-danger" href="logout.php">
                        <span class="me-2 text-center" style="width:20px;">
                            <i class="fas fa-sign-out-alt"></i>
                        </span>
                        Sign Out
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>