# Course Files (local_coursefiles)

Web service functions that let external tools – for example a teacher's own dashboard or an AI assistant connected
through the [MCP web service plugin](https://github.com/onbirdev/moodle-webservice_mcp) – put files into a course
and show or hide activities.

Moodle core cannot create file resources or folders through web services, and `core_course_edit_module`
(show/hide) is only available to the Moodle interface itself. This plugin adds:

| Function | What it does |
| --- | --- |
| `local_coursefiles_create_resource` | Creates a file resource (e.g. a PDF worksheet) in a course section. |
| `local_coursefiles_create_folder` | Creates a folder with several files in a course section. |
| `local_coursefiles_update_files` | Replaces the file of a resource or all files of a folder. The activity keeps its ID, place, settings and completion. |
| `local_coursefiles_set_visibility` | Shows or hides any activity or resource. |

Files are sent in the request as `{filename, content}` with the content base64 encoded. Each file must not be larger
than the upload limit of the course. New resources and folders are hidden unless `visible=1`.

## Requirements

- Moodle 5.0 or later
- Web services enabled

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

## License

GNU GPL v3 or later. © 2026 Holger Sebastião
