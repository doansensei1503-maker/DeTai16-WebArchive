<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\LibraryRole;
use App\Service\AccessControl;
use App\Service\LibraryService;
use App\Service\TagService;
use App\Support\Database;
use Tests\DatabaseTestCase;

/**
 * CN1: Thu vien, nhan va phan quyen (co that - luu vao MySQL).
 */
final class LibraryAndTagTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();
    }

    public function testTaoThuVienThanhCong(): void
    {
        $owner = $this->createUser('cn1_owner_' . bin2hex(random_bytes(4)));
        $name = 'Thu vien kiem thu ' . bin2hex(random_bytes(4));

        $libraryId = LibraryService::create($owner, $name, 'Mo ta thu vien');

        $this->assertGreaterThan(0, $libraryId);

        $library = LibraryService::find($libraryId);
        $this->assertNotNull($library);
        $this->assertSame($name, $library['name']);
        $this->assertSame($owner, (int) $library['owner_id']);
        $this->assertSame('Mo ta thu vien', $library['description']);
    }

    public function testChuThuienTuDongCoRoleOwner(): void
    {
        $owner = $this->createUser('cn1_own2_' . bin2hex(random_bytes(4)));
        $libraryId = LibraryService::create($owner, 'Lib ' . bin2hex(random_bytes(4)));

        $role = AccessControl::role($libraryId, $owner);

        $this->assertSame(LibraryRole::Owner, $role);
        $this->assertTrue(AccessControl::isOwner($libraryId, $owner));
    }

    public function testTenRongBiTuChoi(): void
    {
        $owner = $this->createUser('cn1_bad_' . bin2hex(random_bytes(4)));

        $this->expectException(\InvalidArgumentException::class);
        LibraryService::create($owner, '   ');
    }

    public function testChinhSuaThuVienKhiCoQuyen(): void
    {
        $owner = $this->createUser('cn1_edit_' . bin2hex(random_bytes(4)));
        $libraryId = LibraryService::create($owner, 'Ten cu', 'Mo ta cu');

        LibraryService::update($libraryId, 'Ten moi', 'Mo ta moi');

        $library = LibraryService::find($libraryId);
        $this->assertSame('Ten moi', $library['name']);
        $this->assertSame('Mo ta moi', $library['description']);
    }

    public function testDanhSachThuVienChiChuaThuVienDuocPhep(): void
    {
        $userA = $this->createUser('cn1_a_' . bin2hex(random_bytes(4)));
        $userB = $this->createUser('cn1_b_' . bin2hex(random_bytes(4)));

        $libA = LibraryService::create($userA, 'Lib A ' . bin2hex(random_bytes(4)));
        LibraryService::create($userB, 'Lib B ' . bin2hex(random_bytes(4)));

        $visible = AccessControl::librariesForUser($userA);

        $this->assertCount(1, $visible, 'User A chi thay thu vien cua minh');
        $this->assertSame($libA, (int) $visible[0]['id']);
    }

    public function testKhongPhanQuyenThiBiTuChoi(): void
    {
        $owner = $this->createUser('cn1_own3_' . bin2hex(random_bytes(4)));
        $other = $this->createUser('cn1_oth3_' . bin2hex(random_bytes(4)));

        $libraryId = LibraryService::create($owner, 'Lib rieng ' . bin2hex(random_bytes(4)));

        $this->assertFalse(AccessControl::canRead($libraryId, $other));
        $this->assertFalse(AccessControl::canWrite($libraryId, $other));
        $this->assertFalse(AccessControl::canManage($libraryId, $other));
        $this->assertNull(AccessControl::role($libraryId, $other));
    }

    public function testPhanQuyenEditorVaViewer(): void
    {
        $owner = $this->createUser('cn1_own4_' . bin2hex(random_bytes(4)));
        $editor = $this->createUser('cn1_ed4_' . bin2hex(random_bytes(4)));
        $viewer = $this->createUser('cn1_vi4_' . bin2hex(random_bytes(4)));

        $libraryId = LibraryService::create($owner, 'Lib phan quyen ' . bin2hex(random_bytes(4)));
        AccessControl::addMember($libraryId, $editor, LibraryRole::Editor);
        AccessControl::addMember($libraryId, $viewer, LibraryRole::Viewer);

        // Editor: doc + ghi, khong quan ly.
        $this->assertTrue(AccessControl::canRead($libraryId, $editor));
        $this->assertTrue(AccessControl::canWrite($libraryId, $editor));
        $this->assertFalse(AccessControl::canManage($libraryId, $editor));

        // Viewer: chi doc.
        $this->assertTrue(AccessControl::canRead($libraryId, $viewer));
        $this->assertFalse(AccessControl::canWrite($libraryId, $viewer));
        $this->assertFalse(AccessControl::canManage($libraryId, $viewer));
    }

    public function testTaoVaGanNhan(): void
    {
        $owner = $this->createUser('cn1_tag_' . bin2hex(random_bytes(4)));
        $libraryId = LibraryService::create($owner, 'Lib nhan ' . bin2hex(random_bytes(4)));

        $tagId = TagService::create($libraryId, 'PHP', '#7b68ee');

        $this->assertGreaterThan(0, $tagId);

        $tags = TagService::listForLibrary($libraryId);
        $this->assertCount(1, $tags);
        $this->assertSame('PHP', $tags[0]['name']);
    }

    public function testNhanKhongDuocTranSangThuVienKhac(): void
    {
        $userA = $this->createUser('cn1_ta_' . bin2hex(random_bytes(4)));
        $libA = LibraryService::create($userA, 'Lib A ' . bin2hex(random_bytes(4)));
        $libB = LibraryService::create($userA, 'Lib B ' . bin2hex(random_bytes(4)));

        $tagId = TagService::create($libA, 'Rieng A');

        // Nhan thuoc libA khong xuat hien trong libB.
        $tagsB = TagService::listForLibrary($libB);
        $this->assertCount(0, $tagsB);

        // Gan nhan sang thu vien khac bi tu choi.
        $this->expectException(\InvalidArgumentException::class);
        TagService::attach(1, $tagId, $libB);
    }

    public function testXoaNhanKhongDuocXoaNhanThuVienKhac(): void
    {
        $userA = $this->createUser('cn1_tb_' . bin2hex(random_bytes(4)));
        $libA = LibraryService::create($userA, 'Lib A ' . bin2hex(random_bytes(4)));
        $libB = LibraryService::create($userA, 'Lib B ' . bin2hex(random_bytes(4)));

        $tagId = TagService::create($libA, 'Can bao ve');

        // Xoa nhan cua libA bang quyen cua libB -> khong duoc xoa.
        TagService::delete($tagId, $libB);

        $this->assertCount(1, TagService::listForLibrary($libA), 'Nhan phai con nguyen trong thu vien goc');
    }

    public function testDoiTenNhan(): void
    {
        $owner = $this->createUser('cn1_tr_' . bin2hex(random_bytes(4)));
        $libraryId = LibraryService::create($owner, 'Lib doi ten ' . bin2hex(random_bytes(4)));

        $tagId = TagService::create($libraryId, 'Ten cu');
        TagService::rename($tagId, $libraryId, 'Ten moi');

        $tags = TagService::listForLibrary($libraryId);
        $this->assertSame('Ten moi', $tags[0]['name']);
    }

    public function testDuLieuThuVienKhongBiTronLan(): void
    {
        $userA = $this->createUser('cn1_iso_a_' . bin2hex(random_bytes(4)));
        $userB = $this->createUser('cn1_iso_b_' . bin2hex(random_bytes(4)));

        $libA = LibraryService::create($userA, 'Thu vien A');
        $libB = LibraryService::create($userB, 'Thu vien B');

        TagService::create($libA, 'Nhan A');
        TagService::create($libB, 'Nhan B');

        $this->assertSame('Nhan A', TagService::listForLibrary($libA)[0]['name']);
        $this->assertSame('Nhan B', TagService::listForLibrary($libB)[0]['name']);
        $this->assertNotSame($libA, $libB);
    }

    public function testXoaThuVienXoaCascading(): void
    {
        $owner = $this->createUser('cn1_del_' . bin2hex(random_bytes(4)));
        $libraryId = LibraryService::create($owner, 'Lib se xoa ' . bin2hex(random_bytes(4)));
        TagService::create($libraryId, 'Nhan se xoa');

        LibraryService::delete($libraryId);

        $this->assertNull(LibraryService::find($libraryId));
        $this->assertCount(0, TagService::listForLibrary($libraryId));
    }
}