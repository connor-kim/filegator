
**important!!: 항상 한글로 기록하고 답변하고 설명해줘**

# AGENTS.md - FileGator 웹하드 프로젝트

## 프로젝트 개요

MySQL 백엔드를 사용하는 PHP 기반 웹 파일 관리자(웹하드). Pure PHP + Vanilla JS + Bootstrap 5. 한국어 UI.

**기술 스택**: PHP 7.4+, MySQL/MariaDB, Bootstrap 5, Vanilla JavaScript

## 구조

```
filegator/
├── admin/           # 관리자 패널 (사용자 관리)
├── file/            # 파일 작업 (CRUD, 업로드, 다운로드, 뷰)
├── inc/             # 공통 include (db, auth, header, footer, nav)
├── repository/      # 파일 저장소 디렉토리 (gitignored)
├── user/            # 사용자 인증 (로그인, 로그아웃, 마이페이지)
├── index.php        # 진입점 (세션 기반 리다이렉트)
├── error.php        # 에러 페이지 핸들러
├── QUERY.sql        # 데이터베이스 스키마
└── SPEC.md          # 프로젝트 사양서 (한글)
```

## 명령어

```bash
# 빌드 시스템 없음 - 순수 PHP
# 로컬 서버 실행:
php -S localhost:8000

# 데이터베이스 설정:
mysql -u root -p < QUERY.sql
```

## 데이터베이스

- **연결**: `inc/db.php` (mysqli)
- **테이블**: `users`, `files`
- **기본 관리자**: username `admin`, password `admin` (bcrypt 해시)

## 코드 스타일

### PHP 규칙
- **여는 태그**: `<?php` (짧은 태그 사용 금지)
- **세션 처리**: `session_start()` 호출 전 항상 `session_status()` 확인
- **Include**: 상대 경로와 함께 `include_once` 사용 (`../inc/db.php`)
- **SQL**: 반드시 `bind_param()`을 사용한 prepared statements 사용
- **출력 이스케이프**: HTML 출력 시 반드시 `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')` 사용
- **에러 처리**: 치명적 에러는 `die()`, HTTP 에러는 `http_response_code()` 사용
- **파일 작업**: 파일 접근 전 `file_exists()` 확인, 업로드는 `is_uploaded_file()` 검증

### 파일 구조 패턴
```php
<?php
session_start();  // 또는 먼저 session_status() 체크
include_once '../inc/db.php';

// 인증 확인
if (!isset($_SESSION['user_id'])) {
    header("Location: ../user/login.php");
    exit();
}

// prepared statements를 사용한 비즈니스 로직
$stmt = $conn->prepare("SELECT ... WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
// ...
$stmt->close();
$conn->close();
?>
```

### HTML/뷰 패턴
```php
<?php include_once '../inc/header.php'; ?>
<?php include_once '../inc/nav.php'; ?>

<div class="content-card">
    <!-- 콘텐츠 작성 -->
</div>

<?php include_once '../inc/footer.php'; ?>
```

### 명명 규칙
- **파일명**: snake_case (`login_process.php`, `user_list.php`)
- **변수명**: snake_case (`$user_id`, `$file_id`, `$is_public`)
- **함수명**: snake_case (`is_logged_in()`, `require_admin()`)
- **DB 컬럼명**: snake_case (`user_id`, `upload_date`, `is_folder`)
- **CSS 클래스**: Bootstrap 5 클래스 사용; 커스텀 클래스는 kebab-case

### 인증
- 세션 변수: `$_SESSION['user_id']`, `$_SESSION['username']`, `$_SESSION['role']`
- `inc/auth.php`의 인증 헬퍼: `is_logged_in()`, `is_admin()`, `require_login()`, `require_admin()`
- Role 값: `'user'` 또는 `'admin'`

### 보안 요구사항
- **SQL 인젝션**: 절대 문자열 연결로 쿼리 작성 금지 - 항상 prepared statements 사용
- **XSS**: 항상 `htmlspecialchars()`로 출력 이스케이프
- **파일 업로드**: `is_uploaded_file()`로 검증, `preg_replace()`로 파일명 정제
- **접근 제어**: 작업 전 `$_SESSION['user_id']`와 파일 소유권 확인
- **CSRF**: 현재 미구현 - 폼 제출 시 추가 고려

### 파일 권한
- 파일은 `is_public` 플래그 보유 (0 = 비공개, 1 = 공개)
- 비공개 파일: 소유자만 접근 가능
- 공개 파일: 직접 링크로 누구나 접근 가능

## 안티패턴

- **절대 금지** `mysql_*` 함수 사용 (deprecated) - `mysqli` 또는 PDO 사용
- **절대 금지** 사용자 입력을 SQL 쿼리에 문자열 연결
- **절대 금지** `htmlspecialchars()` 없이 사용자 데이터 출력
- **절대 금지** `$_FILES['name']`을 직접 신뢰 - 파일명 정제 필수
- **절대 금지** 사용자 제공 경로에 대해 파일 존재 확인 없이 `require` 사용
- **절대 금지** 버전 관리에 데이터베이스 자격증명 노출 (운영 환경에서 `inc/db.php`는 gitignore 처리)

## 중요 파일

| 파일 | 용도 |
|------|------|
| `inc/db.php` | 데이터베이스 연결 (MySQL 자격증명 편집) |
| `inc/auth.php` | 인증 헬퍼 함수 |
| `inc/header.php` | HTML head, Bootstrap CSS, 앱 셸 시작 |
| `inc/nav.php` | 네비게이션 바 컴포넌트 |
| `inc/footer.php` | Footer, Bootstrap JS, 태그 닫기 |
| `QUERY.sql` | 데이터베이스 스키마 - 초기 1회 실행 |

## 업로드 설정

대용량 파일 업로드를 위해 PHP 설정 변경 필요 (`upload_process.php` 또는 `php.ini`):
```php
ini_set('upload_max_filesize', '1024M');
ini_set('post_max_size', '1024M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);
```

## 공통 패턴

### 파일 소유권 확인
```php
$stmt = $conn->prepare("SELECT user_id FROM files WHERE id = ?");
$stmt->bind_param("i", $file_id);
$stmt->execute();
$stmt->bind_result($owner_id);
$stmt->fetch();
if ($_SESSION['user_id'] != $owner_id) {
    die("Access denied");
}
```

### 작업 후 리다이렉트
```php
header("Location: list.php?parent_id=" . $parent_id);
exit();
```

### Parent ID 처리 (폴더 네비게이션용)
```php
$parent_id = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : null;
// SQL에서 NULL-safe 비교를 위해 <=> 사용
$stmt = $conn->prepare("... WHERE parent_id <=> ?");
$stmt->bind_param("i", $parent_id);
```

## 언어

- UI 텍스트: 한국어
- 코드 주석: 한국어 또는 영어 가능
- 변수/함수명: 영어만 사용
