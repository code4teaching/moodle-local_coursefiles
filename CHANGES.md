# Changes

## 0.3.0 (2026-11-12)

- New `backup_course`: a Moodle backup (.mbz) of a course without user data (no users, submissions, grades,
  logs). Refuses if the site forces user data into backups. The file is kept for the calling user only (one per
  course) and is downloaded with the token through `webservice/pluginfile.php`.

## 0.2.0 (2026-11-05)

- New `create_section`, `update_section` and `move_section`: add a course section (at the end or at a position),
  change its name, summary or visibility, and move it with its activities. Moodle core offers none of these
  to web services.

## 0.1.0 (2026-11-05)

- First version: `create_resource`, `create_folder`, `update_files`, `set_visibility`.
