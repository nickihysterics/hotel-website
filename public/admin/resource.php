<?php

declare(strict_types=1);

require __DIR__ . '/../../config/bootstrap.php';

/** @var PDO $pdo */

use App\Repository\CrudRepository;
use App\Security\Auth;
use App\Support\FormValidator;

if (!isset($config) || !is_array($config)) {
    http_response_code(500);
    echo 'Неверная конфигурация страницы.';
    exit;
}

$auth = new Auth($pdo);
$auth->requirePermission((string) ($config['permission'] ?? 'catalog.manage'));

$title = (string) ($config['title'] ?? '');
$fields = $config['fields'] ?? [];
$primaryKey = (string) ($config['primaryKey'] ?? 'id');
$table = (string) ($config['table'] ?? '');

$columns = array_map(static fn (array $field): string => (string) $field['name'], $fields);
$repository = new CrudRepository($pdo, $table, $primaryKey, $columns);

// Конфигурационные callback могут принимать только нужную им часть стандартного контекста.
$callCallback = static function (callable $callback, array $args) {
    $reflector = new ReflectionFunction(Closure::fromCallable($callback));
    $paramCount = $reflector->getNumberOfParameters();
    return $callback(...array_slice($args, 0, $paramCount));
};

$errors = [];
$formValues = [];
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;

if ($editId) {
    $formValues = $repository->find($editId) ?? [];
}

if (is_post()) {
    if (!verify_csrf($_POST['_token'] ?? null)) {
        flash('error', 'Неверный токен безопасности.');
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    $action = (string) ($_POST['action'] ?? 'create');
    $validator = new FormValidator($fields);

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $repository->delete($id);
                audit_log($pdo, 'admin.delete', $table, (string) $id);
                flash('success', 'Запись удалена.');
            } catch (PDOException) {
                flash('error', 'Запись используется в системе. Вместо удаления деактивируйте её.');
            }
        }
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    // Сохранение проходит единый конвейер: базовая проверка → доменная проверка → подготовка → persist.
    [$data, $errors] = $validator->validate($_POST);
    $formValues = array_merge($formValues, $data);
    $recordId = $action === 'update' ? (int) ($_POST['id'] ?? 0) : null;

    if (isset($config['validate']) && is_callable($config['validate'])) {
        $customErrors = $callCallback($config['validate'], [$pdo, $data, $recordId ?: $editId, $action]);
        if (is_array($customErrors)) {
            $errors = array_merge($errors, $customErrors);
        }
    }

    if ($errors === []) {
        if (isset($config['beforeSave']) && is_callable($config['beforeSave'])) {
            $result = $callCallback($config['beforeSave'], [$pdo, $data, $recordId ?: $editId, $action]);
            if (is_array($result)) {
                $data = $result;
            }
        }

        $persisted = false;
        if (isset($config['persist']) && is_callable($config['persist'])) {
            $persistResult = $callCallback($config['persist'], [$pdo, $data, $recordId ?: $editId, $action, $repository]);
            if (is_array($persistResult) && $persistResult !== []) {
                $errors = array_merge($errors, $persistResult);
            } else {
                $persisted = true;
            }
        }

        if ($errors === []) {
            try {
                if (!$persisted) {
                    if ($action === 'update') {
                        $id = (int) ($_POST['id'] ?? 0);
                        if ($id > 0) {
                            $repository->update($id, $data);
                            audit_log($pdo, 'admin.update', $table, (string) $id, $data);
                            flash('success', 'Изменения сохранены.');
                        }
                    } else {
                        $id = $repository->create($data);
                        audit_log($pdo, 'admin.create', $table, (string) $id, $data);
                        flash('success', 'Запись добавлена.');
                    }
                } else {
                    audit_log($pdo, 'admin.' . $action, $table, (string) ($recordId ?? ''), $data);
                    flash('success', $action === 'update' ? 'Изменения сохранены.' : 'Запись добавлена.');
                }

                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
                exit;
            } catch (PDOException $exception) {
                $errors['_form'] = $exception->getCode() === '23000'
                    ? 'Такая запись уже существует или связана с другими данными.'
                    : 'Не удалось сохранить запись. Проверьте заполнение полей.';
            }
        }
    }
}

// Динамические варианты select загружаются только после обработки POST, чтобы сохранить введённые значения.
foreach ($fields as &$field) {
    if (isset($field['options']) && is_callable($field['options'])) {
        $field['options'] = $callCallback($field['options'], [$pdo, $formValues, $editId]);
    }
    if (!isset($field['options']) || !is_array($field['options'])) {
        $field['options'] = $field['options'] ?? [];
    }
}
unset($field);

$listOptions = [];
foreach ($fields as $field) {
    $name = (string) ($field['name'] ?? '');
    $options = null;
    if (isset($field['listOptions'])) {
        if (is_callable($field['listOptions'])) {
            $options = $callCallback($field['listOptions'], [$pdo]);
        } elseif (is_array($field['listOptions'])) {
            $options = $field['listOptions'];
        }
    } elseif (isset($field['options']) && is_array($field['options'])) {
        $options = $field['options'];
    }
    $listOptions[$name] = is_array($options) ? $options : [];
}

$rows = $repository->all();

require __DIR__ . '/../../templates/admin/header.php';
?>
<section class="admin-card">
  <h2><?= e($config['listTitle'] ?? 'Список') ?></h2>
  <div class="table-scroll"><table class="admin-table">
    <thead>
      <tr>
        <?php foreach ($fields as $field): ?>
          <th><?= e($field['label'] ?? $field['name']) ?></th>
        <?php endforeach; ?>
        <th>Действия</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <?php foreach ($fields as $field): ?>
            <?php
              $name = $field['name'];
              $value = $row[$name] ?? '';
              $display = $value;
              $displayOptions = $listOptions[$name] ?? [];
              if (($field['type'] ?? '') === 'select' && isset($displayOptions[$value])) {
                  $display = $displayOptions[$value];
              }
              ?>
            <td><?= e((string) $display) ?></td>
          <?php endforeach; ?>
          <td class="table-actions">
            <a class="button button-link" href="?edit=<?= e((string) $row[$primaryKey]) ?>">Изменить</a>
            <form class="inline-form" method="post" action="" data-confirm="Удалить запись?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= e((string) $row[$primaryKey]) ?>">
              <button class="button button-secondary" type="submit">Удалить</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<section class="admin-card">
  <h2><?= e($config['formTitle'] ?? ($editId ? 'Редактирование' : 'Добавить запись')) ?></h2>
  <form class="admin-form" method="post" action="">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editId ? 'update' : 'create' ?>">
    <?php if ($editId): ?>
      <input type="hidden" name="id" value="<?= e((string) $editId) ?>">
    <?php endif; ?>

    <?php if (isset($errors['_form'])): ?><div class="flash flash-error"><?= e($errors['_form']) ?></div><?php endif; ?>
    <div class="form-grid">
      <?php foreach ($fields as $field): ?>
        <?php
          $name = $field['name'];
          $label = $field['label'] ?? $name;
          $type = $field['type'] ?? 'text';
          $required = (bool) ($field['required'] ?? false);
          $default = $field['default'] ?? '';
          $value = array_key_exists($name, $formValues) ? $formValues[$name] : $default;
          ?>
        <div class="form-field">
          <label for="<?= e($name) ?>"><?= e($label) ?></label>
          <?php if ($type === 'select'): ?>
            <select id="<?= e($name) ?>" name="<?= e($name) ?>" <?= $required ? 'required' : '' ?>>
              <option value="">Выберите...</option>
              <?php foreach ($field['options'] ?? [] as $optionValue => $optionLabel): ?>
                <option value="<?= e((string) $optionValue) ?>" <?= (string) $optionValue === (string) $value ? 'selected' : '' ?>>
                  <?= e((string) $optionLabel) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php elseif ($type === 'textarea'): ?>
            <textarea id="<?= e($name) ?>" name="<?= e($name) ?>" rows="4" <?= $required ? 'required' : '' ?>><?= e((string) $value) ?></textarea>
          <?php else: ?>
            <input
              id="<?= e($name) ?>"
              name="<?= e($name) ?>"
              type="<?= e($type === 'number' ? 'number' : ($type === 'date' ? 'date' : 'text')) ?>"
              value="<?= e((string) $value) ?>"
              <?= $required ? 'required' : '' ?>
              <?= isset($field['step']) ? 'step="' . e((string) $field['step']) . '"' : '' ?>
              <?= isset($field['min']) ? 'min="' . e((string) $field['min']) . '"' : '' ?>
            >
          <?php endif; ?>
          <?php if (isset($errors[$name])): ?>
            <small><?= e($errors[$name]) ?></small>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="form-actions">
      <button class="button" type="submit"><?= $editId ? 'Сохранить' : 'Добавить' ?></button>
      <?php if ($editId): ?>
        <a class="button button-secondary" href="<?= e(strtok($_SERVER['REQUEST_URI'], '?')) ?>">Отменить</a>
      <?php endif; ?>
    </div>
  </form>
</section>
<?php
require __DIR__ . '/../../templates/admin/footer.php';
