# Minecraft Player Manager (Fork)

Original project:
https://github.com/kumagames-fou/minecraft-player-manager

This fork improves RCON connectivity and player status reliability for Pelican Panel Minecraft servers.

---

## FR - Documentation

### Resume
Ce plugin permet de gerer les joueurs Minecraft depuis le panel Pelican:
- Liste des joueurs (Player List)
- Statuts Online/Offline/Banned/OP
- Actions OP/DEOP, Kick, Ban/Unban
- Vue detaillee d'un joueur (stats, inventaire, infos live)

### Modifications apportees dans ce fork
1. Ajout de nouveaux parametres plugin pour RCON:
- `RCON Host / IP`
- `RCON Port`
- `RCON Password`

2. Refactor du service joueur (`MinecraftPlayerProvider`):
- Utilise maintenant la config RCON du plugin (et plus uniquement la logique precedente).
- Repli automatique sur l'envoi de commande panel si RCON n'est pas disponible.
- Detection des joueurs online amelioree (RCON `list` en priorite, puis Query UDP).

3. Stabilisation des statuts joueurs:
- Statut Online/Offline plus fiable.
- Distinction OP/Banned conservee dans la liste et les widgets.
- Recuperation des donnees live via RCON plus robuste (health, food, level, gamemode, inventaire).

4. Correctifs widgets:
- Meilleure robustesse sur les filtres Online/OP/Banned.
- Gestion defensive des valeurs manquantes.

5. Ajout des nouvelles cles de traduction pour les parametres RCON.

### Variables d'environnement utilisees
Le plugin sauvegarde ces variables:
- `MC_PLAYER_MANAGER_RCON_ENABLED`
- `MC_PLAYER_MANAGER_RCON_HOST`
- `MC_PLAYER_MANAGER_RCON_PORT`
- `MC_PLAYER_MANAGER_RCON_PASSWORD`
- `MC_PLAYER_MANAGER_NAV_SORT`

### Configuration recommandee
1. Activer RCON sur le serveur Minecraft (`enable-rcon=true`).
2. Definir le meme mot de passe RCON dans le serveur et dans le plugin.
3. Definir l'IP/Host RCON reachable depuis le panel Pelican.
4. Definir le port RCON correct (souvent `25575`, mais peut varier selon votre config).

### Depannage rapide
Si les joueurs online ne remontent pas:
1. Verifier les champs RCON du plugin.
2. Verifier le pare-feu entre panel et serveur Minecraft.
3. Verifier que le mot de passe RCON est correct.
4. Verifier que le port RCON est ouvert.
5. Verifier que le serveur autorise le Query UDP si vous voulez aussi le fallback Query.

---

## EN - Documentation

### Summary
This plugin manages Minecraft players directly from Pelican Panel:
- Player list
- Online/Offline/Banned/OP status
- OP/DEOP, Kick, Ban/Unban actions
- Detailed player page (stats, inventory, live values)

### Changes included in this fork
1. Added new plugin settings for RCON:
- `RCON Host / IP`
- `RCON Port`
- `RCON Password`

2. Refactored player provider (`MinecraftPlayerProvider`):
- Now uses plugin RCON settings as primary configuration.
- Falls back to panel command dispatch when RCON is unavailable.
- Improved online player detection (RCON `list` first, UDP query as fallback).

3. Improved player status consistency:
- More reliable Online/Offline detection.
- OP/Banned state preserved across list and widgets.
- More robust live data parsing (health, food, level, gamemode, inventory).

4. Widget stability improvements:
- Defensive filtering for Online/OP/Banned tables.
- Better handling for missing boolean fields.

5. Added translation keys for the new RCON settings.

### Environment variables
- `MC_PLAYER_MANAGER_RCON_ENABLED`
- `MC_PLAYER_MANAGER_RCON_HOST`
- `MC_PLAYER_MANAGER_RCON_PORT`
- `MC_PLAYER_MANAGER_RCON_PASSWORD`
- `MC_PLAYER_MANAGER_NAV_SORT`

### Recommended setup
1. Enable RCON in `server.properties` (`enable-rcon=true`).
2. Use the exact same RCON password in server config and plugin settings.
3. Set the RCON host/IP reachable from Pelican panel.
4. Set the correct RCON port (commonly `25575`, unless customized).

### Quick troubleshooting
If online players are not shown:
1. Check RCON host/port/password in plugin settings.
2. Check firewall/network route between panel and Minecraft server.
3. Confirm RCON is enabled and listening.
4. Confirm UDP query support if you rely on query fallback.

---

## DE - Dokumentation

### Zusammenfassung
Dieses Plugin verwaltet Minecraft-Spieler direkt im Pelican Panel:
- Spielerliste
- Online/Offline/Gebannt/OP Status
- OP/DEOP, Kick, Ban/Unban Aktionen
- Detaillierte Spieleransicht (Statistiken, Inventar, Live-Daten)

### Anderungen in diesem Fork
1. Neue Plugin-Einstellungen fur RCON hinzugefugt:
- `RCON Host / IP`
- `RCON Port`
- `RCON Password`

2. `MinecraftPlayerProvider` uberarbeitet:
- Verwendet jetzt primar die RCON-Einstellungen des Plugins.
- Fallback auf Panel-Befehlsversand, wenn RCON nicht verfugbar ist.
- Verbesserte Online-Erkennung (zuerst RCON `list`, dann UDP Query als Fallback).

3. Stabilere Spieler-Statuslogik:
- Zuverlassigere Online/Offline Erkennung.
- OP/Gebannt Status bleibt konsistent.
- Robustere Live-Daten-Auswertung (Health, Food, Level, Gamemode, Inventar).

4. Stabilitatsverbesserungen in Widgets:
- Defensive Filter fur Online/OP/Gebannt Tabellen.
- Bessere Behandlung fehlender Werte.

5. Neue Ubersetzungsschlussel fur RCON-Einstellungen hinzugefugt.

### Umgebungsvariablen
- `MC_PLAYER_MANAGER_RCON_ENABLED`
- `MC_PLAYER_MANAGER_RCON_HOST`
- `MC_PLAYER_MANAGER_RCON_PORT`
- `MC_PLAYER_MANAGER_RCON_PASSWORD`
- `MC_PLAYER_MANAGER_NAV_SORT`

### Empfohlene Einrichtung
1. RCON in `server.properties` aktivieren (`enable-rcon=true`).
2. Dasselbe RCON-Passwort im Server und im Plugin verwenden.
3. Erreichbaren RCON Host/IP fur das Panel eintragen.
4. Korrekten RCON Port setzen (oft `25575`).

---

## JA - README

### 概要
このプラグインは Pelican Panel から Minecraft プレイヤーを管理します:
- プレイヤー一覧
- Online/Offline/Banned/OP ステータス
- OP/DEOP, Kick, Ban/Unban 操作
- プレイヤー詳細表示 (統計, インベントリ, ライブ情報)

### この Fork での主な変更
1. RCON 設定項目を追加:
- `RCON Host / IP`
- `RCON Port`
- `RCON Password`

2. `MinecraftPlayerProvider` を改善:
- プラグイン設定の RCON 情報を優先使用。
- RCON 失敗時はパネル経由コマンド送信にフォールバック。
- オンライン判定を改善 (RCON `list` 優先、UDP Query はフォールバック)。

3. ステータス判定を安定化:
- Online/Offline 判定の信頼性を向上。
- OP/Banned 状態を一覧とウィジェットで一貫保持。
- ライブデータ解析を強化 (体力, 空腹度, レベル, ゲームモード, インベントリ)。

4. ウィジェットの安定性を改善:
- Online/OP/Banned テーブルのフィルタを防御的に修正。

5. 新しい RCON 設定用の翻訳キーを追加。

### 環境変数
- `MC_PLAYER_MANAGER_RCON_ENABLED`
- `MC_PLAYER_MANAGER_RCON_HOST`
- `MC_PLAYER_MANAGER_RCON_PORT`
- `MC_PLAYER_MANAGER_RCON_PASSWORD`
- `MC_PLAYER_MANAGER_NAV_SORT`

### 推奨設定
1. `server.properties` で RCON を有効化 (`enable-rcon=true`)。
2. サーバー側とプラグイン側で同じ RCON パスワードを設定。
3. パネルから到達可能な RCON Host/IP を設定。
4. 正しい RCON ポートを設定 (通常は `25575`)。

---

## Notes
- Date of fork update: 2026-04-20
- Goal of this fork: restore functional RCON connectivity and reliable player status views in PlayerList + Online Players sections.
