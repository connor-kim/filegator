<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PNC 웹하드</title>
    <!-- Bootstrap 5 (CoreUI 데모와 동일한 기반 프레임워크) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <style>
        /* 전체 배경 및 기본 레이아웃 – CoreUI 대시보드와 유사한 느낌 */
        body {
            background-color: #f3f4f6;
            font-size: 0.95rem;
        }

        .app-shell {
            background-color: #f3f4f6;
        }

        .app-main {
            background: transparent;
        }

        /* 콘텐츠 영역 카드 느낌 */
        .app-main .content-card {
            background-color: #ffffff;
            border-radius: .5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(15, 23, 42, 0.08);
            padding: 1.5rem;
        }

        /* 모든 버튼 텍스트를 한 줄로 표시 */
        .btn {
            white-space: nowrap;
        }

        /* 파일 리스트 테이블 공통 스타일 */
        .file-list-table th,
        .file-list-table td {
            text-align: center;
            vertical-align: middle;
        }

        /* 파일 리스트 타이틀(헤더) 배경색 – CoreUI 테이블 헤더 계열 색상 */
        .file-list-table thead th {
            background-color: #e5e7eb;
        }
    </style>
</head>
<body class="bg-body-tertiary">
<div class="app-shell d-flex flex-column min-vh-100">
