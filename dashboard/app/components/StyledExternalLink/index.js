import React, { Component } from 'react';
import styled from 'styled-components';

const StyledExternalLink = styled.a`
    text-decoration: none;
    color: white;
    &:focus, &:hover, &:visited, &:link, &:active {
        text-decoration: none;
        color: white;
    }
`;

export default (props) => <StyledExternalLink {...props} />;