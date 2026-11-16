<?php
use App\Core\Auth;
use App\Core\Security;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="text-white fw-bold m-0">User Management & Role-Based Access</h4>
        <p class="text-secondary small m-0">Manage system administrators and operators with least-privilege security.</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#modalCreateUser">
        <i class="bi bi-person-plus-fill"></i>
        <span>Add User</span>
    </button>
</div>

<div class="card-custom">
    <div class="table-responsive">
        <table class="table table-dark-custom">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><code class="text-secondary">#<?= $u['id'] ?></code></td>
                        <td><strong class="text-white"><?= Security::e($u['name']) ?></strong></td>
                        <td><?= Security::e($u['email']) ?></td>
                        <td>
                            <span class="badge <?= $u['role'] === 'admin' ? 'bg-primary' : 'bg-secondary' ?>">
                                <?= ucfirst($u['role']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-status status-<?= $u['status'] === 'active' ? 'published' : 'failed' ?>">
                                <?= $u['status'] ?>
                            </span>
                        </td>
                        <td class="text-secondary small"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalEditUser_<?= $u['id'] ?>" title="Edit Role/Status">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if ($u['id'] !== Auth::id()): ?>
                                    <form action="<?= $app['url'] ?>/users/<?= $u['id'] ?>/delete" method="POST" class="d-inline" onsubmit="return confirm('Delete this user?');">
                                        <?= $csrf_field ?>
                                        <button type="submit" class="btn btn-outline-danger" title="Delete User">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <!-- Edit Modal -->
                            <div class="modal fade text-start" id="modalEditUser_<?= $u['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title text-white">Edit User: <?= Security::e($u['name']) ?></h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form action="<?= $app['url'] ?>/users/<?= $u['id'] ?>/status" method="POST">
                                            <?= $csrf_field ?>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Role</label>
                                                    <select name="role" class="form-select">
                                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin (Full Access)</option>
                                                        <option value="operator" <?= $u['role'] === 'operator' ? 'selected' : '' ?>>Operator (Upload & Manage Videos)</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select name="status" class="form-select">
                                                        <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                        <option value="inactive" <?= $u['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                        <option value="suspended" <?= $u['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Create User -->
<div class="modal fade" id="modalCreateUser" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-white">Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= $app['url'] ?>/users" method="POST">
                <?= $csrf_field ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="User Name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required placeholder="user@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" minlength="8" required placeholder="••••••••">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="operator" selected>Operator (Upload & Manage Videos)</option>
                            <option value="admin">Administrator (Full Control)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>
