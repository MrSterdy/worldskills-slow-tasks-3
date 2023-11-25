<?php
$tmp_dir = dirname($_SERVER["SCRIPT_FILENAME"]);
$tmp = get_absolute_path($tmp_dir . "/" . $_REQUEST["file"]);

$file = $_REQUEST["file"] ?: ".";

if ($_GET["do"] == "list") {
    if (is_dir($file)) {
        $directory = $file;
        $result = [];
        $files = array_diff(scandir($directory), [".", ".."]);
        foreach ($files as $entry) {
            if (!is_entry_ignored($entry)) {
                $i = $directory . "/" . $entry;
                $stat = stat($i);
                $result[] = [
                    "mtime" => $stat["mtime"],
                    "size" => $stat["size"],
                    "name" => basename($i),
                    "path" => preg_replace("@^\./@", "", $i),
                    "is_dir" => is_dir($i),
                    "is_deleteable" =>
                        (!is_dir($i) && is_writable($directory)) ||
                        (is_dir($i) &&
                            is_writable($directory) &&
                            is_recursively_deleteable($i)),
                    "is_readable" => is_readable($i),
                    "is_writable" => is_writable($i),
                    "is_executable" => is_executable($i),
                ];
            }
        }
        usort($result, function ($f1, $f2) {
            $f1_key = ($f1["is_dir"] ?: 2) . $f1["name"];
            $f2_key = ($f2["is_dir"] ?: 2) . $f2["name"];
            return $f1_key > $f2_key;
        });
    } else {
        err(412, "Not a Directory");
    }
    echo json_encode([
        "success" => true,
        "is_writable" => is_writable($file),
        "results" => $result,
    ]);
    exit();
} elseif ($_POST["do"] == "delete") {
    rmrf($file);
    exit();
} elseif ($_POST["do"] == "mkdir") {
    // don't allow actions outside root. we also filter out slashes to catch args like './../outside'
    $dir = $_POST["name"];
    $dir = str_replace("/", "", $dir);
    if (str_starts_with($dir, "..")) {
        exit();
    }
    chdir($file);
    @mkdir($_POST["name"]);
    exit();
} elseif ($_POST["do"] == "upload") {
    $res = move_uploaded_file(
        $_FILES["file_data"]["tmp_name"],
        $file . "/" . $_FILES["file_data"]["name"]
    );
    exit();
} elseif ($_GET["do"] == "download") {
    $filename = basename($file);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    header("Content-Type: " . finfo_file($finfo, $file));
    header("Content-Length: " . filesize($file));
    header(
        sprintf(
            "Content-Disposition: attachment; filename=%s",
            strpos("MSIE", $_SERVER["HTTP_REFERER"])
                ? rawurlencode($filename)
                : "\"$filename\""
        )
    );
    ob_flush();
    readfile($file);
    exit();
}

function is_entry_ignored($entry)
{
    return $entry === basename(__FILE__);
}

function rmrf($dir)
{
    if (is_dir($dir)) {
        $files = array_diff(scandir($dir), [".", ".."]);
        foreach ($files as $file) {
            rmrf("$dir/$file");
        }
        rmdir($dir);
    } else {
        unlink($dir);
    }
}
function is_recursively_deleteable($d)
{
    $stack = [$d];
    while ($dir = array_pop($stack)) {
        if (!is_readable($dir) || !is_writable($dir)) {
            return false;
        }
        $files = array_diff(scandir($dir), [".", ".."]);
        foreach ($files as $file) {
            if (is_dir($file)) {
                $stack[] = "$dir/$file";
            }
        }
    }
    return true;
}

// from: http://php.net/manual/en/function.realpath.php#84012
function get_absolute_path($path)
{
    $path = str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $path);
    $parts = explode(DIRECTORY_SEPARATOR, $path);
    $absolutes = [];
    foreach ($parts as $part) {
        if ("." == $part) {
            continue;
        }
        if (".." == $part) {
            array_pop($absolutes);
        } else {
            $absolutes[] = $part;
        }
    }
    return implode(DIRECTORY_SEPARATOR, $absolutes);
}

function err($code, $msg)
{
    http_response_code($code);
    header("Content-Type: application/json");
    echo json_encode(["error" => ["code" => intval($code), "msg" => $msg]]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">

    <title>Файловый менеджер</title>

    <style>
        body {
            font-family: "lucida grande", "Segoe UI", Arial, sans-serif;
            font-size: 14px;
            padding: 1em;
            margin: 0;
        }

        th {
            font-weight: normal;
            color: #1F75CC;
            background-color: #F0F9FF;
            padding: .5em 1em .5em .2em;
            text-align: left;
            cursor: pointer;
            user-select: none;
        }

        th .indicator {
            margin-left: 6px
        }

        thead {
            border-top: 1px solid #82CFFA;
            border-bottom: 1px solid #96C4EA;
            border-left: 1px solid #E7F2FB;
            border-right: 1px solid #E7F2FB;
        }

        #top {
            height: 52px;
        }

        #mkdir {
            display: inline-block;
            float: right;
            padding-top: 16px;
        }

        label {
            display: block;
            font-size: 11px;
            color: #555;
        }

        #file_drop_target {
            width: 500px;
            padding: 12px 0;
            border: 4px dashed #ccc;
            font-size: 12px;
            color: #ccc;
            text-align: center;
            float: right;
            margin-right: 20px;
        }

        #file_drop_target.drag_over {
            border: 4px dashed #96C4EA;
            color: #96C4EA;
        }

        #upload_progress {
            padding: 4px 0;
        }

        #upload_progress .error {
            color: #a00;
        }

        #upload_progress>div {
            padding: 3px 0;
        }

        .no_write #mkdir,
        .no_write #file_drop_target {
            display: none
        }

        .progress_track {
            display: inline-block;
            width: 200px;
            height: 10px;
            border: 1px solid #333;
            margin: 0 4px 0 10px;
        }

        .progress {
            background-color: #82CFFA;
            height: 10px;
        }

        footer {
            font-size: 11px;
            color: #bbbbc5;
            padding: 4em 0 0;
            text-align: left;
        }

        footer a,
        footer a:visited {
            color: #bbbbc5;
        }

        #breadcrumb {
            padding-top: 34px;
            font-size: 15px;
            color: #aaa;
            display: inline-block;
            float: left;
        }

        #folder_actions {
            width: 50%;
            float: right;
        }

        a,
        a:visited {
            color: #00c;
            text-decoration: none
        }

        a:hover {
            text-decoration: underline
        }

        .sort_hide {
            display: none;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        thead {
            max-width: 1024px
        }

        td {
            padding: .2em 1em .2em .2em;
            border-bottom: 1px solid #def;
            height: 30px;
            font-size: 12px;
            white-space: nowrap;
        }

        td.first {
            font-size: 14px;
            white-space: normal;
        }

        td.empty {
            color: #777;
            font-style: italic;
            text-align: center;
            padding: 3em 0;
        }

        .is_dir .size {
            color: transparent;
            font-size: 0;
        }

        .is_dir .size:before {
            content: "--";
            font-size: 14px;
            color: #333;
        }

        .is_dir .download {
            visibility: hidden
        }

        a.delete {
            display: inline-block;
            color: #d00;
            margin-left: 15px;
            font-size: 11px;
        }
    </style>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
    <script>
        (function($) {
            $.fn.tableSorter = function() {
                const $table = this;
                this.find('th').click(function() {
                    const idx = $(this).index();
                    const direction = $(this).hasClass('sort_asc');
                    $table.tableSortBy(idx, direction);
                });
                return this;
            };
            $.fn.tableSortBy = function(idx, direction) {
                const $rows = this.find('tbody tr');

                function elementToVal(a) {
                    const $a_elem = $(a).find('td:nth-child(' + (idx + 1) + ')');
                    const a_val = $a_elem.attr('data-sort') || $a_elem.text();
                    return (a_val === parseInt(a_val) ? parseInt(a_val) : a_val);
                }
                $rows.sort(function(a, b) {
                    const a_val = elementToVal(a),
                        b_val = elementToVal(b);
                    return (a_val > b_val ? 1 : (a_val === b_val ? 0 : -1)) * (direction ? 1 : -1);
                })
                this.find('th').removeClass('sort_asc sort_desc');
                $(this).find('thead th:nth-child(' + (idx + 1) + ')').addClass(direction ? 'sort_desc' : 'sort_asc');
                for(let i = 0; i < $rows.length; i++) this.append($rows[i]);
                this.setTableSortMarkers();
                return this;
            }
            $.fn.retableSort = function() {
                const $e = this.find('thead th.sort_asc, thead th.sort_desc');
                if($e.length) this.tableSortBy($e.index(), $e.hasClass('sort_desc'));
                return this;
            }
            $.fn.setTableSortMarkers = function() {
                this.find('thead th span.indicator').remove();
                this.find('thead th.sort_asc').append('<span class="indicator">&darr;<span>');
                this.find('thead th.sort_desc').append('<span class="indicator">&uarr;<span>');
                return this;
            }
        })(jQuery);
        $(function() {
            const $tbody = $('#list');
            $(window).on('hashchange', list).trigger('hashchange');
            $('#table').tableSorter();
            $('#table').on('click', '.delete', function(data) {
                $.post("?", {
                    'do': 'delete',
                    file: $(this).attr('data-file')
                }, function(response) {
                    list();
                }, 'json');
                return false;
            });
            $('#mkdir').submit(function(e) {
                const hashval = decodeURIComponent(window.location.hash.slice(1)),
                    $dir = $(this).find('[name=name]');
                e.preventDefault();
                $dir.val().length && $.post('?', {
                    'do': 'mkdir',
                    name: $dir.val(),
                    file: hashval
                }, function(data) {
                    list();
                }, 'json');
                $dir.val('');
                return false;
            });
            // file upload stuff
            $('#file_drop_target').on('dragover', function() {
                $(this).addClass('drag_over');
                return false;
            }).on('dragend', function() {
                $(this).removeClass('drag_over');
                return false;
            }).on('drop', function(e) {
                e.preventDefault();
                const files = e.originalEvent.dataTransfer.files;
                $.each(files, function(k, file) {
                    uploadFile(file);
                });
                $(this).removeClass('drag_over');
            });
            $('input[type=file]').change(function(e) {
                e.preventDefault();
                $.each(this.files, function(k, file) {
                    uploadFile(file);
                });
            });

            function uploadFile(file) {
                const folder = decodeURIComponent(window.location.hash.slice(1));
                const $row = renderFileUploadRow(file, folder);
                $('#upload_progress').append($row);
                const fd = new FormData();
                fd.append('file_data', file);
                fd.append('file', folder);
                fd.append('do', 'upload');
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '?');
                xhr.onload = function() {
                    $row.remove();
                    list();
                };
                xhr.upload.onprogress = function(e) {
                    if(e.lengthComputable) {
                        $row.find('.progress').css('width', (e.loaded / e.total * 100 | 0) + '%');
                    }
                };
                xhr.send(fd);
            }

            function renderFileUploadRow(file, folder) {
                return $row = $('<div/>').append($('<span class="fileuploadname" />').text((folder ? folder + '/' : '') + file.name)).append($('<div class="progress_track"><div class="progress"></div></div>')).append($('<span class="size" />').text(formatFileSize(file.size)))
            }

            function list() {
                const hashval = window.location.hash.slice(1);
                $.get('?do=list&file=' + hashval, function(data) {
                    $tbody.empty();
                    $('#breadcrumb').empty().html(renderBreadcrumbs(hashval));
                    if(data.success) {
                        $.each(data.results, function(k, v) {
                            $tbody.append(renderFileRow(v));
                        });
                        !data.results.length && $tbody.append('<tr><td class="empty" colspan=5>Папка пустая</td></tr>')
                        data.is_writable ? $('body').removeClass('no_write') : $('body').addClass('no_write');
                    } else {
                        console.warn(data.error.msg);
                    }
                    $('#table').retableSort();
                }, 'json');
            }

            function renderFileRow(data) {
                const $link = $('<a class="name" />').attr('href', data.is_dir ? '#' + encodeURIComponent(data.path) : './' + data.path).text(data.name);
                const $dl_link = $('<a/>').attr('href', '?do=download&file=' + encodeURIComponent(data.path)).addClass('download').text('Скачать');
                const $delete_link = $('<a href="#" />').attr('data-file', data.path).addClass('delete').text('Удалить');
                const perms = [];
                if(data.is_readable) perms.push('Чтение');
                if(data.is_writable) perms.push('Запись');
                if(data.is_executable) perms.push('Запуск');
                return $('<tr />').addClass(data.is_dir ? 'is_dir' : '').append($('<td class="first" />').append($link)).append($('<td/>').attr('data-sort', data.is_dir ? -1 : data.size).html($('<span class="size" />').text(formatFileSize(data.size)))).append($('<td/>').attr('data-sort', data.mtime).text(formatTimestamp(data.mtime))).append($('<td/>').text(perms.join('+'))).append($('<td/>').append($dl_link).append(data.is_deleteable ? $delete_link : ''));
            }

            function renderBreadcrumbs(path) {
                let base = "",
                    $html = $('<div/>').append($('<a href=#>Главная</a></div>'));
                $.each(path.split('%2F'), function(k, v) {
                    if(v) {
                        const v_as_text = decodeURIComponent(v);
                        $html.append($('<span/>').text(' ▸ ')).append($('<a/>').attr('href', '#' + base + v).text(v_as_text));
                        base += v + '%2F';
                    }
                });
                return $html;
            }

            function formatTimestamp(unix_timestamp) {
                const m = ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'];
                const d = new Date(unix_timestamp * 1000);
                return [m[d.getMonth()], ' ', d.getDate(), ', ', d.getFullYear(), " ", d.getHours(), ":", (d.getMinutes() < 10 ? '0' : '') + d.getMinutes()
                ].join('');
            }

            function formatFileSize(bytes) {
                const s = ['байт', 'КБ', 'МБ', 'ГБ', 'ТБ'];
                let pos = 0;
                for(; bytes >= 1000; pos++, bytes /= 1024);
                const d = Math.round(bytes * 10);
                return pos ? [parseInt(d / 10), ".", d % 10, " ", s[pos]].join('') : bytes + ' байт';
            }
        })
    </script>
</head>

<body>
<div id="top">
    <form action="?" method="post" id="mkdir">
        <label for=dirname>Создать папку</label><input id=dirname type=text name=name value="" />
        <input type="submit" value="Создать" />
    </form>
    <div id="file_drop_target">Переместите файлы сюда <b>или</b>
        <input type="file" multiple />
    </div>
    <div id="breadcrumb">&nbsp;</div>
</div>
<div id="upload_progress"></div>
<table id="table">
    <thead>
    <tr>
        <th>Название</th>
        <th>Размер</th>
        <th>Дата редактирования</th>
        <th>Права</th>
        <th>Действия</th>
    </tr>
    </thead>
    <tbody id="list">
    </tbody>
</table>
</body>

</html>