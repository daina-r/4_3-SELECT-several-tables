<?php
session_start();
if (empty($_SESSION)) {
    echo '<a href="login.php">Войти на сайт</a>';
    exit;
}

$dsn = 'mysql:dbname=dmpronin;host=localhost;charset=utf8';
$user = 'dmpronin';
$password = 'neto1740';
$taskStatus = 0;
$description = '';
$infoText = '';
$editTaskDesc = '';
$login = $_SESSION['login'];
$loginId = $_SESSION['login_id'];
const TASK_IN_PROCESS = 1;
const TASK_IS_DONE = 2;

try {
	$db = new PDO($dsn, $user, $password);
} catch (PDOException $e) {
    echo 'Подключение не удалось: ' . $e->getMessage();
}

/*Функция возвращает название статуса*/
function getStatusName($param)
{
    switch($param) {
        case TASK_IN_PROCESS:
            return '<span style="color:orange">в процессе</span>';
            break;

        case TASK_IS_DONE:
            return '<span style="color:green">выполнено</span>';
            break;

        default:
            return '';
            break;
    }
}

/*Функция возвращает список пользователей с ID*/
function getUsersList($db)
{
    $sqlSelect = "SELECT id, login FROM user";
    $statement = $db->prepare($sqlSelect);
    $statement->execute();
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/*Добавляем задачу в список*/
if(!empty($_POST['description']) && empty($_GET['action'])) {
    $description = $_POST['description'];

    $sqlAdd = "INSERT INTO task (user_id, assigned_user_id, description, is_done, date_added) VALUES (?, ?, ?, ?, NOW())";
    $statement = $db->prepare($sqlAdd);
    $statement->execute([$loginId, $loginId, $description, TASK_IN_PROCESS]);
} elseif(isset($_POST['description']) && empty($_POST['description'])) {
    $infoText = 'Вы не заполнили поле "Описание задачи". Задача не добавлена.';
}

/*Выполняем операции с задачами*/
if(!empty($_GET['id']) && !empty($_GET['action'])) {
    $taskID = $_GET['id'];
    $action = $_GET['action'];
    switch($action) {
        case 'done':
            $sqlUpdate = "UPDATE task SET is_done = ? WHERE id = ?";
            $statement = $db->prepare($sqlUpdate);
            $statement->execute([TASK_IS_DONE, $taskID]);
            header('Location: index.php');
            break;

        case 'delete':
            $sqlDelete = "DELETE FROM task WHERE id = ?";
            $statement = $db->prepare($sqlDelete);
            $statement->execute([$taskID]);
            header('Location: index.php');
            break;

        case 'edit':
            $sqlSelectDesc = "SELECT description FROM task WHERE id = ?";
            $statement = $db->prepare($sqlSelectDesc);
            $statement->execute([$taskID]);
            $taskArray = $statement->fetch();
            $editTaskDesc = $taskArray['description'];
            if(!empty($action) && !empty($_POST['descEdit'])) {
                $updatedDesc = $_POST['description'];
                $sqlUpdate = "UPDATE task SET description = ? WHERE id = ?";
                $statement = $db->prepare($sqlUpdate);
                $statement->execute([$updatedDesc, $taskID]);
                header('Location: index.php');
            }
    }
}

/*Меняем ответственного*/
if(!empty($_POST['setAssigned'])) {
    $assignedArr = explode('_', $_POST['assigned_user_id']);

    $sqlUpdate = "UPDATE task SET assigned_user_id = ? WHERE id = ?";
    $statement = $db->prepare($sqlUpdate);
    $statement->execute([$assignedArr[1], $assignedArr[3]]);
}

/*Выводим список добавленных дел в зависимости от сортировки*/
if(!empty($_POST['sort']) && !empty($_POST['sort_by'])) {
    $sortBy = $_POST['sort_by'];
    switch($sortBy) {
        case 'description':
            $sortByForSql = 'description';
            break;
        case 'is_done':
            $sortByForSql = 'is_done';
            break;
        default:
            $sortByForSql = 'date_added';
            break;
    }
    $sqlSelect = "SELECT task.id, task.description, task.is_done, task.date_added, author.login AS author, assigned.login AS assigned
                  FROM task
                  JOIN user AS author ON author.id = task.user_id
                  JOIN user AS assigned ON assigned.id = task.assigned_user_id
                  WHERE task.user_id = $loginId
                  ORDER BY $sortByForSql";
} else {
    $sqlSelect = "SELECT task.id, task.description, task.is_done, task.date_added, author.login AS author, assigned.login AS assigned
                  FROM task
                  JOIN user AS author ON author.id = task.user_id
                  JOIN user AS assigned ON assigned.id = task.assigned_user_id
                  WHERE task.user_id = $loginId
                  ORDER BY date_added";
}

$statement = $db->prepare($sqlSelect);
$statement->execute();

/*Выводим список задач назначенных на пользователя*/
$sqlSelectAssigned = "SELECT task.id, task.description, task.is_done, task.date_added, author.login AS author, assigned.login AS assigned
              FROM task
              JOIN user AS author ON author.id = task.user_id
              JOIN user AS assigned ON assigned.id = task.assigned_user_id
              WHERE task.user_id <> $loginId AND task.assigned_user_id = $loginId
              ORDER BY date_added";

$sth = $db->prepare($sqlSelectAssigned);
$sth->execute();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="UTF-8">
	<title>Список дел на сегодня</title>

    <style>
        table {
            border-collapse: collapse;
            border: 1px solid;
        }

        th {
            background-color: #eeeeee;
        }

        th, td {
            padding: 4px 10px;
            border: 1px solid;
        }
        
        form {
            display: inline-block;
            margin-right: 10px;
        }
    </style>
</head>
<body>
	<h1>Здравствуйте, <?=$_SESSION['login']?>! Ваш список дел на сегодня</h1>

	<form method="POST">
		<input name="description" type="text" placeholder="Описание задачи" value="<?=$editTaskDesc?>">
		<input name="descEdit" type="submit" value="<?=(empty($editTaskDesc) ? 'Добавить' : 'Сохранить')?>">
	</form>

    <form method="POST">
        <label for="sort_by">Сортировать по: </label>
        <select name="sort_by" id="sort_by">
            <option <?= (!empty($_POST['sort_by']) && $_POST['sort_by'] === 'date_added') ? 'selected' : '' ?> value="date_added">дате добавления</option>
            <option <?= (!empty($_POST['sort_by']) && $_POST['sort_by'] === 'is_done') ? 'selected' : '' ?> value="is_done">статусу</option>
            <option <?= (!empty($_POST['sort_by']) && $_POST['sort_by'] === 'description') ? 'selected' : '' ?> value="description">описанию</option>
        </select>
        <input name="sort" type="submit" value="Отфильтровать">
    </form>

    <p style="color: red"><?=$infoText?></p>

	<table>
		<tr>
			<th>Описание задачи</th>
			<th>Дата добавления</th>
			<th>Статус</th>
			<th>Операции</th>
            <th>Ответственный</th>
            <th>Автор</th>
            <th>Закрепить задачу за пользователем</th>
		</tr>
        <?php while($task = $statement->fetch(PDO::FETCH_ASSOC)) : ?>
        <tr>
            <td><?=$task['description']?></td>
            <td><?=$task['date_added']?></td>
            <td><?= getStatusName($task['is_done']) ?></td>
            <td>
                <?php if($task['assigned'] === $login) : ?>
                <a href="index.php?id=<?=$task['id']?>&action=done">Выполнить</a>
                <?php endif;?>
                <a href="index.php?id=<?=$task['id']?>&action=edit">Изменить</a>
                <a href="index.php?id=<?=$task['id']?>&action=delete">Удалить</a>
            </td>
            <td><?=($task['assigned'] === $login) ? 'Вы' : $task['assigned']?></td>
            <td><?=$task['author']?></td>
            <td>
                <form method="POST">
                    <label for="user_select"></label>
                    <select name="assigned_user_id" id="user_select">
                        <?php
                        $userList = getUsersList($db);
                        foreach ($userList as $user) :
                        ?>
                            <option value="user_<?=$user['id']?>_task_<?=$task['id']?>"><?=$user['login']?></option>
                        <?php
                        endforeach;
                        ?>
                    </select>

                    <input name="setAssigned" type="submit" value="сделать ответственным">
                </form>
            </td>
        </tr>
         <?php endwhile; ?>
	</table>

    <h3>Задачи, назначенные на меня другими пользователями</h3>
    <table>
        <tr>
            <th>Описание задачи</th>
            <th>Дата добавления</th>
            <th>Статус</th>
            <th>Операции</th>
            <th>Ответственный</th>
            <th>Автор</th>
        </tr>
        <?php while($taskAssigned = $sth->fetch(PDO::FETCH_ASSOC)) : ?>
            <tr>
                <td><?=$taskAssigned['description']?></td>
                <td><?=$taskAssigned['date_added']?></td>
                <td><?= getStatusName($taskAssigned['is_done']) ?></td>
                <td>
                    <a href="index.php?id=<?=$taskAssigned['id']?>&action=done">Выполнить</a>
                    <a href="index.php?id=<?=$taskAssigned['id']?>&action=edit">Изменить</a>
                    <a href="index.php?id=<?=$taskAssigned['id']?>&action=delete">Удалить</a>
                </td>
                <td><?=$taskAssigned['assigned']?></td>
                <td><?=$taskAssigned['author']?></td>
            </tr>
        <?php endwhile; ?>
    </table>
    <br>
    <a href="logout.php">Выйти</a>
</body>
</html>