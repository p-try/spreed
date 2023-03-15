# Dummy setup.py file to be used by stdeb; setuptools >= 61.0 must be used to
# get the proper configuration from the pyproject.toml file.

from setuptools import setup

setup(
    # pyproject.toml uses different keywords that are not properly converted to
    # the old ones, so they need to be explicitly set here to be used by stdeb.
    # "author" and "author_email" can not be set, though, as due to how they are
    # internally handled by stdeb it ends mixing the author set here with the
    # author and email set in pyproject.toml.
    url = "https://github.com/nextcloud/spreed",
)
