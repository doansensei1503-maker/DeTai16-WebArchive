<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\LibraryRole;
use App\Service\AccessControl;
use App\Service\UserService;
use App\Support\Security;

final class LibraryMemberController extends BaseController
{
    public function index(int $id): void
    {
        $this->requireAuth();

        if (!AccessControl::canManage($id)) {
            $this->backWithErrors(['general' => 'Bạn không có quyền quản lý thành viên.']);
        }

        $library = \App\Service\LibraryService::find($id);
        if ($library === null) {
            $this->backWithErrors(['general' => 'Thư viện không tồn tại.']);
        }

        $members = AccessControl::membersOf($id);

        $this->render('libraries/members.php', [
            'title'    => 'Thành viên · ' . (string) $library['name'],
            'library'  => $library,
            'members'  => $members,
            'csrf'     => $this->csrfField(),
        ]);
    }

    public function store(int $id): void
    {
        $this->requireAuth();

        if (!AccessControl::canManage($id)) {
            $this->backWithErrors(['general' => 'Bạn không có quyền thêm thành viên.']);
        }

        if (!Security::verifyToken($_POST['_token'] ?? '')) {
            $this->backWithErrors(['general' => 'Phiên làm việc không hợp lệ.']);
        }

        $username = trim($_POST['username'] ?? '');
        $role = LibraryRole::tryFrom($_POST['role'] ?? '') ?? LibraryRole::Viewer;

        if ($username === '') {
            $this->backWithErrors(['username' => 'Tên đăng nhập không được để trống.']);
        }

        $user = UserService::findByUsername($username);
        if ($user === null) {
            $this->backWithErrors(['username' => "Không tìm thấy người dùng \"{$username}\"."]);
        }

        $userId = (int) $user['id'];

        // Khong cho them chu thu vien 2 lan.
        if (AccessControl::role($id, $userId) !== null) {
            $this->backWithErrors(['username' => 'Người dùng này đã có trong thư viện.']);
        }

        AccessControl::addMember($id, $userId, $role);
        $this->success("Đã thêm \"{$user['username']}\" với vai trò {$role->label()}.");
        $this->redirect("/libraries/{$id}/members");
    }

    public function update(int $id, int $userId): void
    {
        $this->requireAuth();

        if (!AccessControl::canManage($id)) {
            $this->backWithErrors(['general' => 'Bạn không có quyền thay đổi vai trò.']);
        }

        if (!Security::verifyToken($_POST['_token'] ?? '')) {
            $this->backWithErrors(['general' => 'Phiên làm việc không hợp lệ.']);
        }

        $role = LibraryRole::tryFrom($_POST['role'] ?? '');
        if ($role === null) {
            $this->backWithErrors(['role' => 'Vai trò không hợp lệ.']);
        }

        // Khong cho thay doi vai tro chu thu vien.
        if (AccessControl::isOwner($id, $userId)) {
            $this->backWithErrors(['general' => 'Không thể thay đổi vai trò của chủ sở hữu.']);
        }

        AccessControl::addMember($id, $userId, $role);
        $this->success('Cập nhật vai trò thành công!');
        $this->redirect("/libraries/{$id}/members");
    }

    public function destroy(int $id, int $userId): void
    {
        $this->requireAuth();

        if (!AccessControl::canManage($id)) {
            $this->backWithErrors(['general' => 'Bạn không có quyền xóa thành viên.']);
        }

        if (!Security::verifyToken($_POST['_token'] ?? '')) {
            $this->backWithErrors(['general' => 'Phiên làm việc không hợp lệ.']);
        }

        if (AccessControl::isOwner($id, $userId)) {
            $this->backWithErrors(['general' => 'Không thể xóa chủ sở hữu khỏi thư viện.']);
        }

        AccessControl::removeMember($id, $userId);
        $this->success('Đã xóa thành viên khỏi thư viện.');
        $this->redirect("/libraries/{$id}/members");
    }
}