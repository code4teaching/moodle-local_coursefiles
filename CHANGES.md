# Changes

## 0.13.0 (2026-11-20)

- `create_assign` and `update_assign` set how an assignment is graded: maximum points, no grade (`grade` 0), or a
  scale by name (`gradescale`, a site scale or one of the course), and the marking workflow (`markingworkflow`), so
  students see grades only once they are released. The grade type can only change while nobody has a grade.
- New `get_assign_grading`: grade type with the scale items, marking workflow and, per participant, grade, workflow
  state, feedback comment and number of feedback files.
- New `grade_submission`: grades one participant – grade, feedback comment and marking workflow state.
- New `release_grades`: releases grades (all graded participants or the ones given), so they reach the gradebook.

## 0.12.0 (2026-11-19)

- New `update_assign`: changes an existing assignment – name, task description, attached files (keep some, add or
  replace others) and due date. Only what is passed changes; submissions, grades and all other settings stay.

## 0.11.0 (2026-11-19)

- New `create_assign`: creates an assignment in a section – file submissions, a due date, the task as text and
  attached files (e.g. the worksheet), feedback comments and feedback files switched on. Hidden by default.
- New `set_assign_duedate`: changes the due date of an assignment (calendar event included); a cut-off date before the
  new due date is removed.

## 0.10.0 (2026-11-19)

- New `set_feedback_files`: gives one student feedback files in an assignment (e.g. the corrected, annotated
  submission), replacing earlier feedback files of this student. Needs `mod/assign:grade`; the feedback type
  "Feedback files" must be enabled in the assignment. The grade itself is not changed.

## 0.9.1 (2026-11-19)

- `set_course_visibility` returns the new visibility from the database (not the cached course).

## 0.9.0 (2026-11-19)

- New `set_course_visibility`: shows or hides a whole course, checking `moodle/course:visibility` – teachers can
  publish their own course without getting `core_course_update_courses`, which can change much more.

## 0.8.0 (2026-11-19)

- New `set_availability`: plans when an activity or resource becomes available – a date restriction "from" at the top
  level (replacing an earlier one, other restrictions stay), and the activity is made visible so Moodle releases it by
  itself. `hidebefore` (default 1) hides it completely before the date. `timefrom=0` removes the date again. Refused
  when restricted access is turned off on the site.

## 0.7.0 (2026-11-19)

- New page `local/coursefiles/connect.php`: connects VisuDesk without copying a key. The teacher signs in to Moodle
  as usual (single sign-on too), confirms, and Moodle hands the token for the service VisuDesk to the app on the same
  computer – like the launch of the Moodle mobile app. The app sends a random passport and accepts only a token that
  comes back with it; the token only goes to a loopback address (127.0.0.1, localhost) on the path `/verbunden`.
  Moodle checks as on the security keys page whether the user may create a token for the service. German strings.

## 0.6.0 (2026-11-18)

- New `get_self_enrolment` and `set_self_enrolment`: read and set the self enrolment of a course – on or off and the
  enrolment key. If the course has no self enrolment method yet, it is added with the site defaults (role: student).
  Moodle core only offers students a web service for self enrolment, none for teachers to configure it. Respects
  *Use password policy* and *Require enrolment key* of the self enrolment plugin.

## 0.5.1 (2026-11-13)

- Saving the setting now really gives the role to all current teachers (the callback was not reachable).
- Site admins do not get the role; they have every capability anyway.

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
