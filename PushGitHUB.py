
import subprocess
import sys
from pathlib import Path
from datetime import datetime

# =====================================
# НАСТРОЙКИ
# =====================================

GITHUB_REPO="https://github.com/zubludok/minecraft_pmmp_5_44_3_worldgenerator.git"

BRANCH="main"

COMMIT_PREFIX="Auto update"

# =====================================
# ОСНОВНОЙ КОД
# =====================================

FOLDER=Path(__file__).resolve().parent

def git(*args):
    result=subprocess.run(
        ["git",*args],
        cwd=FOLDER,
        capture_output=True,
        text=True
    )

    if result.returncode!=0:
        raise RuntimeError(result.stderr.strip())

    return result.stdout.strip()


def main():
    print("=== GitHub Auto Push ===")
    print(f"Папка: {FOLDER}")

    # Проверяем наличие Git
    try:
        git("--version")
    except Exception:
        print("ОШИБКА: Git не установлен!")
        return

    # Инициализация репозитория
    if not (FOLDER/".git").exists():
        print("Инициализация Git...")
        git("init","-b",BRANCH)

    # Устанавливаем ветку
    git("branch","-M",BRANCH)

    # Настраиваем адрес репозитория
    remotes=git("remote")

    if "origin" in remotes.splitlines():
        git("remote","set-url","origin",GITHUB_REPO)
    else:
        git("remote","add","origin",GITHUB_REPO)

    # Добавляем файлы
    print("Добавление файлов...")
    git("add","-A")

    # Проверяем изменения
    changes=git("diff","--cached","--quiet") if False else None

    result=subprocess.run(
        ["git","diff","--cached","--quiet"],
        cwd=FOLDER
    )

    if result.returncode==0:
        print("Новых изменений нет.")

    else:
        # Создаём коммит
        commit_time=datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        message=f"{COMMIT_PREFIX} {commit_time}"

        print("Создание коммита...")
        git("commit","-m",message)

    # Отправляем на GitHub
    print("Отправка на GitHub...")
    git("push","-u","origin",BRANCH)

    print("\nУСПЕШНО! Репозиторий обновлён.")


if __name__=="__main__":
    try:
        main()
    except Exception as e:
        print(f"\nОШИБКА: {e}")

    input("\nНажми Enter для выхода...")