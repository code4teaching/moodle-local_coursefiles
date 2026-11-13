# Changes

## 0.5.0 (2026-11-13)

- New setting *Site role for teachers*: everybody who has the teacher role in a course gets a chosen system role
  (e.g. "Web service user" with `moodle/webservice:createtoken` and `webservice/mcp:use`), because such
  capabilities are checked for the whole site and cannot come from a course role. An event observer assigns the
  role when someone becomes a teacher; a daily task and saving the setting catch everybody else. Off by default.

## 0.4.0 (2026-11-13)

- New `request_course`, `get_course_requests`, `approve_course_request` and `reject_course_request`: Moodle course
  requests through web services. Teachers ask for a course; approvers list all pending requests, approve them with a
  corrected name and another category, or reject them with a reason. Moodle core sends the notifications and enrols
  the requester as teacher, exactly as on `course/pending.php`. Needs *Enable course requests* in the site settings.

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
