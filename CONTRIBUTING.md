# Contributing to MapCentia Open Source projects

Please note we have a code of conduct, please follow it in all your interactions with the project.

## Submitting bugs
### Due diligence
Before submitting a bug, please do the following:

- Perform basic troubleshooting steps:

    - Make sure you’re on the latest version (Master branch). If you’re not on the most recent version, your problem may have been solved already! Upgrading is always the best first step.

    - [Search the issue tracker](https://github.com/mapcentia/vidi/issues) to make sure it’s not a known issue.

### What to put in your bug report
Make sure your report gets the attention it deserves: bug reports with missing information may be ignored or punted back to you, delaying a fix. The below constitutes a bare minimum; more info is almost always better:

- Are you using the official Docker images? If not, which versions of PHP7, PostgreSQL, PostGIS, MapServer etc. and OS (Linux, Windows, MacOS) are you using. if applicable, which browser are you using? (Chrome 70, Firefox 62, Edge 17, etc.)

- How can the developers recreate the bug on their end? If possible, include a copy of your data (e.g. Shape file), link to online server with the bug, and the full error output from logs (if applicable.)

    - A common tactic is to pare down your setup until a simple (but still bug-causing) “base case” remains. Not only can this help you identify problems which aren’t real bugs, but it means the developer can get to fixing the bug faster.

## Contributing changes
### Licensing of contributed material
Your contribution will be under our [license](https://github.com/mapcentia/vidi/blob/master/LICENSE) as per GitHub's [terms of service](https://help.github.com/articles/github-terms-of-service/#6-contributions-under-repository-license).

## Version control branching
- Always make a new branch for your work, no matter how small. This makes it easy for others to take just that one set of changes from your repository, in case you have multiple unrelated changes floating around.

    - A corollary: don’t submit unrelated changes in the same branch/pull request! The maintainer shouldn’t have to reject your awesome bugfix because the feature you put in with it needs more review.

- Base your new branch off of the appropriate branch on the main repository:

    - Bug fixes should be based on the branch named after the oldest supported release line the bug affects.

        - E.g. if a feature was introduced in 2018.1, the latest release line is 2018.1.4, and a bug is found in that feature - make your branch based on 2018.1. The maintainer will then forward-port it to 2018.1.4 and master.

        - Bug fixes requiring large changes to the code or which have a chance of being otherwise disruptive, may need to base off of master instead. This is a judgement call – ask the devs!

    - New features should branch off of the ‘master’ branch.

        - Note that depending on how long it takes for the dev team to merge your patch, the copy of master you worked off of may get out of date! If you find yourself ‘bumping’ a pull request that’s been sidelined for a while, make sure you rebase or merge to latest master to ensure a speedier resolution.

## Documentation isn’t optional
Pull requests without adequate documentation will be rejected. By "documentation" we mean:

- The pull request should be commented, so its clear what it does.

- Update the CHANGELOG.md with details of the changes. 

- Increase the version numbers in CHANGELOG.md to the new version that this Pull Request would represent. The versioning scheme we use is [CalVer](https://calver.org/) using YYYY.MINOR.MICRO.MODIFIER.
   
## Code formatting
Follow the style you see used in the primary repository! Consistency with the rest of the project always trumps other considerations. It doesn’t matter if you have your own style or if the rest of the code breaks with the greater community - just follow along.

## Suggesting Enhancements
We welcome suggestions for enhancements, but reserve the right to reject them if they do not follow future plans for GC2.

## Code of Conduct

### Our Pledge

In the interest of fostering an open and welcoming environment, we as
contributors and maintainers pledge to making participation in our project and
our community a harassment-free experience for everyone, regardless of age, body
size, disability, ethnicity, gender identity and expression, level of experience,
nationality, personal appearance, race, religion, or sexual identity and
orientation.

### Our Standards

Examples of behavior that contributes to creating a positive environment
include:

* Using welcoming and inclusive language
* Being respectful of differing viewpoints and experiences
* Gracefully accepting constructive criticism
* Focusing on what is best for the community
* Showing empathy towards other community members

Examples of unacceptable behavior by participants include:

* The use of sexualized language or imagery and unwelcome sexual attention or
advances
* Trolling, insulting/derogatory comments, and personal or political attacks
* Public or private harassment
* Publishing others' private information, such as a physical or electronic
  address, without explicit permission
* Other conduct which could reasonably be considered inappropriate in a
  professional setting

### Our Responsibilities

Project maintainers are responsible for clarifying the standards of acceptable
behavior and are expected to take appropriate and fair corrective action in
response to any instances of unacceptable behavior.

Project maintainers have the right and responsibility to remove, edit, or
reject comments, commits, code, wiki edits, issues, and other contributions
that are not aligned to this Code of Conduct, or to ban temporarily or
permanently any contributor for other behaviors that they deem inappropriate,
threatening, offensive, or harmful.

### Scope

This Code of Conduct applies both within project spaces and in public spaces
when an individual is representing the project or its community. Examples of
representing a project or community include using an official project e-mail
address, posting via an official social media account, or acting as an appointed
representative at an online or offline event. Representation of a project may be
further defined and clarified by project maintainers.

### Enforcement

Instances of abusive, harassing, or otherwise unacceptable behavior may be
reported by contacting the project team at info@mapcentia.com. All
complaints will be reviewed and investigated and will result in a response that
is deemed necessary and appropriate to the circumstances. The project team is
obligated to maintain confidentiality with regard to the reporter of an incident.
Further details of specific enforcement policies may be posted separately.

Project maintainers who do not follow or enforce the Code of Conduct in good
faith may face temporary or permanent repercussions as determined by other
members of the project's leadership.

### Attribution

This Code of Conduct is adapted from the [Contributor Covenant][homepage], version 1.4,
available at [http://contributor-covenant.org/version/1/4][version]

[homepage]: http://contributor-covenant.org
[version]: http://contributor-covenant.org/version/1/4/