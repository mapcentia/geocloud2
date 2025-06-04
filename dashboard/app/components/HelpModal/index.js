import React from 'react';
import {makeStyles} from '@material-ui/core/styles';
import Modal from '@material-ui/core/Modal';
import {Dialog} from "@material-ui/core";
import {DialogTitle} from "@material-ui/core";
import {DialogContent} from "@material-ui/core";
import {DialogContentText} from "@material-ui/core";
import {DialogActions} from "@material-ui/core";
import {Button} from "@material-ui/core";
import { FormattedMessage } from 'react-intl';
import HelpIcon from "@material-ui/icons/Help";

function rand() {
    return Math.round(Math.random() * 20) - 10;
}


class HelpModal extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            open: false
        };


    }

    render() {
        return(
        <div>
            <Button color="inherit" onClick={()=>this.setState({open:true})}>
                <HelpIcon style={{marginRight: `6px`}}/>
            </Button>
            <Dialog
                open={this.state.open}
                onClose={()=>this.setState({open:false})}
                fullWidth={true}
            >
                <DialogContent>
                    <iframe style={{width: "100%", border: "none"}} src="https://mapcentia.github.io/docs_landing_page/index.html"></iframe>
                </DialogContent>
                <DialogActions>
                    <Button onClick={()=>this.setState({open:false})} color="primary">
                         <FormattedMessage id="Close"/>
                    </Button>
                </DialogActions>
            </Dialog>
        </div>)
    };
}

export default HelpModal;