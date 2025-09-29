# Fantasy Formula 1 App

A fantasy F1 application where users create championships, select drivers within budget constraints, and compete based on real F1 race results.

## Tech Stack (Beta)

- **Frontend**: React
- **Backend**: PHP + SQLite (file-based)
- **Database**: SQLite with 13 tables
- **Real-time**: Frontend API polling
- **Hosting**: Existing PHP hosting

## Core Features

- **User Management**: Registration, login, profiles
- **Championship Creation**: Users can create and admin their own fantasy leagues
- **Driver Selection**: AI-calculated pricing before each race, 6-driver teams
- **Flexible Scoring**: Complex position gain/loss point system with bonuses/malus
- **Budget Management**: ~250 budget per race with possible overrides
- **Real F1 Integration**: Import race results to calculate fantasy points
- **DRS Strategy**: Optional multiplier bonus with cap

## Database Structure

See `db_structure_plan.md` for complete schema details (13 tables, flexible rules system).

---

## Pages layout and design

### Header

- logo on the top left. It's present on all pages

### Footer

- present in all pages except the login page and it serves as a navigation with all icons

## App layout

### Login page

    - username input
    - password input
    - login button
    - link to registration page
    - link to password recovery page
    - remember me checkbox

### Dashboard

- list of championships the user is participating in
  - each championship card shows:
    - championship name and year
    - championship status (in progress, finished, upcoming)
    - user's position in the championship
    - button to view championship details (link to championship details page)

### Championship details page

    - championship name and year
    - championship status (in progress, finished, upcoming)
        - name/nickname
        - team if any
        - wrapper with position in the championship and points
        - wrapper with next race details
            - date
            - track name
            - country
            - countdown timer until last moment to set up a team
            - button to set up a team (link to team setup page)

### Team Setup Page

    - budget available bar that increases/decreases as drivers are selected/deselected
    - if budget is exceeded, the bar turns red
    - DRS checkbox off/on UI. Automatic forced choice. If qualify time is not exceeded then the DRS is on. When qualify starts it will be automatically be off
    - shows the total price of the selected drivers
    - team selection section
        - empty slots for drivers (6 slots)
        - each slot is clickable to open a modal with a list of available drivers
        - each slot shows a placeholder image when empty
        - each slot shows the selected driver's image, name, team, and price when filled
            - driver number as a logo
            - wrapper with name and lastname of the driver and a 3px left border with the team color
            - team
            - price
    - save button to save the team (disabled if budget is exceeded or not all slots are filled)
    - all choices are saved in local storage until the user clicks the save button

### History page

    - list of accordions with each championship the user has participated in
        - each accordion shows the championship name and year
        - different UI if the championship is won (gold border and trophy icon)
        - different UI if the championship is in progress
        - when expanded, it shows a table with the following columns:
            - position
            - name/nickname
            - team
            - points
