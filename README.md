# BTD
British Traffic Department (BTD) Website

# User Manual (see pdf in documentation for pictures)

Hello Police Officers! This is a tour of the BTD website which you will be using for your
daily tasks. Each of you will be provided with an Officer ID (Badge number), Username
and Password which would be used to login to the site.

- **Login**

```
You must enter the username and password provided to you to login to the site.
```

- **Dashboard**
    This is the first screen you see after logging in. This contains information about
    the recent incidents filed and a quick stats section.

```
You can navigate to any task you want to complete using the sidebar.
```
- **Sidebar**
    The sidebar is where you can access all the functionalities of the webpage.


```
Use the BTD button on top to open and close the sidebar. You can access your
profile details, search people, vehicle, incident, add new person, vehicle,
incident, ownership.
```
- **Profile**
    This is where you will find your personal details and an option to change the
    password. Contact admin to change any details.


- **Change Password**
    To change the password, you need to provide the current password, enter the
    new password, re-enter new password and hit update password.
- **People Search**
    You can search for any person currently available in the system by providing
    complete or even part of the person’s name or licence number. This will show
    you all results which has your input as part of the name or licence number. You
    can edit the person’s details by pressing the edit button next to the result.


- **Vehicle Search**
    Works similarly to the people search. Returns all results that have complete or
    part of the search input plate number.
- **Incident Search**
    The incident search also works similarly to the people and vehicle searches.
    Returns incidents of the same Incident ID and the incidents reported by the
    officers with the same Officer ID, ordered by date, newest to oldest. You also
    have the option to delete an incident. You can delete or edit any incident, but
    every action is recorded and reviewed. For longer reports you can hover over the
    report to view the whole report.


- **Add Person**
    You can create a new person record by adding at least the person’s name and
    licence number. Since the licence number is the only true way to identify a
    person, it is mandatory. If a person does not have a licence number, you can add
    their name as a placeholder in the licence number field and edit the person
    details when the person attains a licence number. When there are clashes,
    check address. Edit Person works on the same principle and looks like this page.


- **Add Vehicle**
    Adding a new vehicle works similarly to Add Person, the only difference being all
    fields are mandatory to create a new record. This is to ensure consistency within
    the database. Edit vehicle works on the same principle and looks like this page


- **New Incident**
    The new incident report is designed to be a quick way to file reports. The only
    mandatory fields here are the date and the incident report. If you decide to add
    information about the vehicle or the person, the fields will autofill if the entity is
    recorded in the database, else you must add their details, and the new entity will
    be created along with the incident. Edit incident works on the same principle
    and looks like this page.


- **New Ownership**
    This page lets you establish an ownership between a vehicle and a person. Each
    vehicle can only have one owner, and each person can own multiple vehicles.
    The fields in this form are handled the same way as in New Incident Report.


## ADMIN TOOLS

- **New User**
    You can create a new user/officer with the help of this form. You can add a user
    without the officer details if the user is not an officer (for tech department).
    Record the Officer ID to be provided to the officer. You can also grant admin
    privileges under the role section.


- **New Offence**
    If a new offence needs to be recorded to abide with law regulations, you can do
    so with this form. All fields are mandatory.


- **New Fine**
    This form lets you associate a fine with an incident. You can select any incident
    that does not have an associated fine and the maximum amount and points will
    be shown depending on the offence. All fields mandatory.


- **Audit Trail**
    You can view all changes to the database in this section. You can filter by
    Username, Action, Table and Dates. Each result comes with a button to view the
    details of the change.

```
Note: Contact admin to make changes to any information you do not have
edit/delete permissions for.
```