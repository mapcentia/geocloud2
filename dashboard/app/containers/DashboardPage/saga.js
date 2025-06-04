import { takeLatest } from 'redux-saga/effects';

export function* getRepos() {}

export default function* githubData() {
  yield takeLatest(`LOAD_REPOS`, getRepos);
}
