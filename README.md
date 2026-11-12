# Course Files (local_coursefiles)

Web service functions that let external tools – for example a teacher's own dashboard or an AI assistant connected
through the [MCP web service plugin](https://github.com/onbirdev/moodle-webservice_mcp) – put files into a course
and show or hide activities.

Moodle core cannot create file resources or folders through web services, and `core_course_edit_module`
(show/hide) and section editing are only available to the Moodle interface itself. This plugin adds:

| Function | What it does |
| --- | --- |
| `local_coursefiles_create_resource` | Creates a file resource (e.g. a PDF worksheet) in a course section. |
| `local_coursefiles_create_folder` | Creates a folder with several files in a course section. |
| `local_coursefiles_update_files` | Replaces the file of a resource or all files of a folder. The activity keeps its ID, place, settings and completion. |
| `local_coursefiles_set_visibility` | Shows or hides any activity or resource. |
| `local_coursefiles_create_section` | Creates a section at the end or at a given position. |
| `local_coursefiles_update_section` | Changes name, summary or visibility of a section. |
| `local_coursefiles_backup_course` | Creates a Moodle backup (.mbz) of a course **without user data** and returns a download address. |
| `local_coursefiles_move_section` | Moves a section, with its activities, to another position. |
| `local_coursefiles_request_course` | Asks for a new course (Moodle course request). |
| `local_coursefiles_get_course_requests` | Lists the caller's pending requests, or all of them for approvers. |
| `local_coursefiles_approve_course_request` | Approves a request, optionally with a corrected name and another category. |
| `local_coursefiles_reject_course_request` | Rejects a request; the reason is sent to the requester. |

Files are sent in the request as `{filename, content}` with the content base64 encoded. Each file must not be larger
than the upload limit of the course. New resources and folders are hidden unless `visible=1`.

## Requirements

- Moodle 5.0 or later
- Web services enabled
- For the course request functions: *Site administration → Courses → Course request → Enable course requests*

## Installation

1. Install the plugin as `local/coursefiles`.
2. Site administration → Server → Web services → External services: open **Course Files Service** and add the
   users who may use it – or add the functions to your own custom service.
3. Create a token for such a user and service.

## Permissions

Every function checks the capabilities of the token's user, as the Moodle interface does:

- `create_resource`: `moodle/course:manageactivities` and `mod/resource:addinstance`
- `create_folder`: `moodle/course:manageactivities` and `mod/folder:addinstance`
- `update_files`: `moodle/course:manageactivities` in the activity
- `set_visibility`: `moodle/course:activityvisibility` in the activity
- `create_section`, `update_section`: `moodle/course:update` (visibility also `moodle/course:sectionvisibility`)
- `move_section`: `moodle/course:movesections` and `moodle/course:update`
- `backup_course`: `moodle/backup:backupcourse` and `moodle/backup:downloadfile` (teachers have both by default)
- `request_course`: `moodle/course:request` (authenticated users have it by default; users who can create courses
  themselves cannot request one, as in Moodle)
- `get_course_requests`: own requests for everyone; `all=1` needs `moodle/site:approvecourse`
- `approve_course_request`, `reject_course_request`: `moodle/site:approvecourse`

## License

GNU GPL v3 or later. © 2026 Holger Sebastião
