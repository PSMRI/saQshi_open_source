# Shared Resources

## Purpose

The **Resources** page provides a single library of letters, forms, documents,
guidelines, videos and other reference files for authenticated SaQshi users.
It is available from the main sidebar to every logged-in role.

## Viewing and Downloading Resources

1. Select **Resources** in the sidebar.
2. Select **All** or a facility-type tab such as CHC, DH, PHC, UPHC, HWC,
   PHC without bed, SDH or **Others**.
3. Use the search box to find a resource by its name or description.
4. Select the download icon for the required row.

The table shows the resource name, file type, applicable facility type, number
of downloads and the upload date. The download count increases after a
successful download.

## Publishing Resources

Only a **State Admin** (role 9) can publish or delete a resource. The upload
section is not shown to other users.

When publishing a resource, provide a resource name, file type, applicable
facility type, a file, and an optional description. All file extensions are
accepted. A single file can be up to **500 MB**. The upload screen displays
transfer progress and refreshes the resource table when publishing finishes.

## Applicability and Security

Facility-type choices are maintained in `api/config/masters/facility_types.json`.
Select **Others** when the resource does not apply to one of the listed types.

Files are stored outside the public web directory and require login to download.
Do not publish patient-identifiable information, passwords, private keys or
unapproved files.
