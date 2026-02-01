<?php
ini_set('upload_max_filesize', '1024M');
ini_set('post_max_size', '1024M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);
// 디버깅을 위해 에러 표시 활성화
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include_once '../inc/db.php';

if (!isset($_SESSION['user_id'])) {
    die("파일을 업로드하려면 로그인해야 합니다.");
}

if (isset($_FILES['files'])) {
    $user_id = $_SESSION['user_id'];
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    $total_files = count($_FILES['files']['name']);

    // 업로드된 파일 정보 기본 체크
    if ($total_files === 0) {
        echo "업로드된 파일이 없습니다. (total_files = 0)<br>";
        var_dump($_FILES['files']);
        exit;
    }

    // 저장소 경로 및 퍼미션 확인
    $repoPath = realpath('../repository');
    //echo "저장소 실제 경로: " . ($repoPath ?: '해당 없음(realpath 실패)') . "<br>";
    //echo "저장소 디렉토리 존재 여부: " . (is_dir('../repository') ? '예' : '아니오') . "<br>";
    //echo "저장소 디렉토리 쓰기 가능 여부: " . (is_writable('../repository') ? '예' : '아니오') . "<br>";

    for ($i = 0; $i < $total_files; $i++) {
        $filename = basename($_FILES['files']['name'][$i]);
        // Generate a unique stored filename to avoid collisions
        $safeFilename = preg_replace('/[^\w\-.]+/', '_', $filename);
        $uniquePrefix = date('YmdHis') . '_' . bin2hex(random_bytes(4));
        $storedFilename = $uniquePrefix . '_' . $safeFilename;
        $filepath = '../repository/' . $storedFilename;

        //echo "<hr>";
        //echo "인덱스 {$i} 파일 처리 중...<br>";
        //echo "원본 파일명: {$filename}<br>";
        //echo "저장 파일명: {$storedFilename}<br>";
        //echo "저장 전체 경로: {$filepath}<br>";
        //echo "임시 파일 경로(tmp_name): " . $_FILES['files']['tmp_name'][$i] . "<br>";

        // Check if file with the same name already exists for the user in the same folder
        $stmt = $conn->prepare("SELECT id FROM files WHERE user_id = ? AND filename = ? AND parent_id <=> ?");
        $stmt->bind_param("isi", $user_id, $filename, $parent_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo "File with the same name '{$filename}' already exists in this folder. Skipping.<br>";
            continue; // Skip this file
        }
        $stmt->close();

        // PHP 업로드 에러 코드 체크
        $error_code = $_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($error_code !== UPLOAD_ERR_OK) {
            echo "파일 {$filename} 업로드 중 오류 발생 (error code: {$error_code})<br>";
            continue;
        }

        // 실제 업로드된 파일인지 확인
        if (!is_uploaded_file($_FILES['files']['tmp_name'][$i])) {
            echo "임시 업로드 파일을 찾을 수 없습니다: {$filename}<br>";
            var_dump($_FILES['files']['tmp_name'][$i]);
            continue;
        }

        //echo "move_uploaded_file 호출 전, 대상 경로: {$filepath}<br>";

        if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $filepath)) {
            echo "move_uploaded_file 성공. 파일이 저장되었습니다.<br>";

            $sql = "INSERT INTO files (user_id, filename, filepath, is_public, parent_id) VALUES (?, ?, ?, ?, ?)";
            //echo "실행 예정 DB 쿼리 (prepared): {$sql}<br>";
            //echo "바인딩 파라미터: user_id={$user_id}, filename={$filename}, filepath={$filepath}, is_public={$is_public}, parent_id=" . var_export($parent_id, true) . "<br>";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                echo "쿼리 준비 실패: " . $conn->error . "<br>";
            } else {
                $stmt->bind_param("issii", $user_id, $filename, $filepath, $is_public, $parent_id);

                if (!$stmt->execute()) {
                    echo "DB INSERT 실행 중 오류 (파일: {$filename}): " . $stmt->error . "<br>";
                } else {
                    echo "DB INSERT 성공 (파일: {$filename})<br>";
                }
                $stmt->close();
            }
        } else {
            echo "Sorry, there was an error uploading your file {$filename}.<br>";
            echo "move_uploaded_file 실패. 저장 경로: {$filepath}<br>";
            var_dump($_FILES['files']);
            $last_error = error_get_last();
            if ($last_error) {
                echo "PHP 마지막 에러: " . $last_error['message'] . "<br>";
            }
        }
    }

    // 업로드 후 리스트로 돌아가는 코드는 디버깅을 위해 주석 처리
     $redirect_url = "list.php";
     if ($parent_id) {
         $redirect_url .= "?parent_id=" . $parent_id;
     }
     header("Location: " . $redirect_url);

} else {
    echo "No file uploaded.";
}

$conn->close();
?>
