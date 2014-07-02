My Contacts API
===============

_Author: Vito Tardia (<vito@tardia.me>)_

This application implements a simple contact list service that manages contacts with linked notes. It has **two object types**, contacts and notes. Each contact has basic attributes such as first name, last name, and email address. Also, each contact can have a number of markdown-formatted notes linked to it.

**This is sample code** for the article "Constructing a full REST API with respect to best practices - Part 1 and 2" written for Sitepoint.

## Resources and Actions

    URL                           HTTP Method  Operation
    /api/contacts                 GET          Returns an array of contacts
    /api/contacts/:id             GET          Returns the contact with id of :id
    /api/contacts                 POST         Adds a new contact and return it with an id attribute added
    /api/contacts/:id             PUT          Updates the contact with id of :id
    /api/contacts/:id             PATCH        Partially updates the contact with id of :id
    /api/contacts/:id             DELETE       Deletes the contact with id of :id

    /api/contacts/:id/star        PUT          Adds to favorites  the contact with id of :id
    /api/contacts/:id/star        DELETE       Removes from favorites  the contact with id of :id

    /api/contacts/:id/notes       GET          Returns the notes for the contact with id of :id
    /api/contacts/:id/notes/:nid  GET          Returns the note with id of :nid for the contact with id of :id
    /api/contacts/:id/notes       POST         Adds a new note for the contact with id of :id
    /api/contacts/:id/notes/:nid  PUT          Updates the note with id if :nid for the contact with id of :id
    /api/contacts/:id/notes/:nid  PATCH        Partially updates the note with id of :nid for the contact with id of :id
    /api/contacts/:id/notes/:nid  DELETE       Deletes the note with id of :nid for the contact with id of :id
