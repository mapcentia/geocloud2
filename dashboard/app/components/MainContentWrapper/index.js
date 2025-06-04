import React, { Component } from 'react';
import styled from 'styled-components';

const MainContentWrapper = styled.div`
margin: 0 auto;
padding: 10px 10px 10px;

@media (min-width: 948px) {
    width: 900px;
    margin-left: auto;
    margin-right: auto;
}`;

export default MainContentWrapper;